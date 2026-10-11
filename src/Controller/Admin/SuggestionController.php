<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGeneratorInterface;

/**
 * Generation from the product page, and accept / reject decisions (single or bulk). Every
 * action is a CSRF-protected POST that redirects back: nothing is injected in the product
 * form, the page is reloaded with the accepted text.
 */
final class SuggestionController extends AbstractController
{
    public const GENERATE_CSRF_TOKEN_ID = 'tmonier_ai_copilot_generate';

    public const DECIDE_CSRF_TOKEN_ID = 'tmonier_ai_copilot_decide';

    public const MAX_BULK_ITEMS = 100;

    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AiSuggestionRepositoryInterface $suggestionRepository,
        private readonly SuggestionGeneratorInterface $suggestionGenerator,
        private readonly SuggestionApplierInterface $suggestionApplier,
    ) {
    }

    /**
     * The "target" parameter is "field|locale" or "field|locale|source locale" (translation).
     */
    public function generateAction(Request $request, int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof ProductInterface) {
            throw new NotFoundHttpException('Product not found.');
        }
        $this->assertCsrfToken($request, self::GENERATE_CSRF_TOKEN_ID);

        $target = explode('|', $request->request->getString('target'));
        if (count($target) < 2 || count($target) > 3 || '' === $target[0] || '' === $target[1]) {
            throw new BadRequestHttpException('Invalid generation target.');
        }
        [$field, $localeCode] = $target;
        $sourceLocaleCode = isset($target[2]) && '' !== $target[2] ? $target[2] : null;

        try {
            $this->suggestionGenerator->generate($product, $field, $localeCode, $sourceLocaleCode, $this->getAdminUser());
            $this->addFlash('success', 'tmonier_ai_copilot.suggestion_generated');
        } catch (SuggestionException $exception) {
            $this->addFlash('error', ['message' => $exception->getMessage(), 'parameters' => $exception->getParameters()]);
        }

        return $this->redirect($this->generateUrl('sylius_admin_product_update', ['id' => $id]) . '#tmonier-ai-copilot');
    }

    public function decideAction(Request $request, int $id, string $decision): Response
    {
        $suggestion = $this->suggestionRepository->find($id);
        if (!$suggestion instanceof AiSuggestionInterface) {
            throw new NotFoundHttpException('Suggestion not found.');
        }
        $this->assertCsrfToken($request, self::DECIDE_CSRF_TOKEN_ID);

        try {
            $this->decide($suggestion, $decision);
            $this->addFlash('success', 'accept' === $decision ? 'tmonier_ai_copilot.suggestion_accepted' : 'tmonier_ai_copilot.suggestion_rejected');
        } catch (SuggestionException $exception) {
            $this->addFlash('error', ['message' => $exception->getMessage(), 'parameters' => $exception->getParameters()]);
        }

        return $this->redirectBack($request);
    }

    /**
     * Grid bulk action: "ids[]" holds the selected suggestions (copied by the Sylius admin
     * "data-bulk-delete" script). Suggestions that cannot be decided are skipped and counted.
     */
    public function bulkDecideAction(Request $request, string $decision): Response
    {
        $this->assertCsrfToken($request, self::DECIDE_CSRF_TOKEN_ID);

        $ids = [];
        foreach ($request->request->all()['ids'] ?? [] as $value) {
            if (is_scalar($value) && ctype_digit((string) $value)) {
                $ids[] = (int) $value;
            }
        }
        $ids = array_values(array_unique($ids));
        if ([] === $ids) {
            $this->addFlash('error', 'tmonier_ai_copilot.bulk_nothing_selected');

            return $this->redirectBack($request);
        }
        if (count($ids) > self::MAX_BULK_ITEMS) {
            $this->addFlash('error', ['message' => 'tmonier_ai_copilot.bulk_too_many', 'parameters' => ['%max%' => self::MAX_BULK_ITEMS]]);

            return $this->redirectBack($request);
        }

        $done = 0;
        $skipped = 0;
        foreach ($this->suggestionRepository->findByIds($ids) as $suggestion) {
            try {
                $this->decide($suggestion, $decision);
                ++$done;
            } catch (SuggestionException) {
                ++$skipped;
            }
        }
        $skipped += count($ids) - $done - $skipped;

        if ($done > 0) {
            $this->addFlash('success', [
                'message' => 'accept' === $decision ? 'tmonier_ai_copilot.bulk_accepted' : 'tmonier_ai_copilot.bulk_rejected',
                'parameters' => ['%count%' => $done],
            ]);
        }
        if ($skipped > 0) {
            $this->addFlash('warning', ['message' => 'tmonier_ai_copilot.bulk_skipped', 'parameters' => ['%count%' => $skipped]]);
        }

        return $this->redirectBack($request);
    }

    private function decide(AiSuggestionInterface $suggestion, string $decision): void
    {
        if ('accept' === $decision) {
            $this->suggestionApplier->accept($suggestion, $this->getAdminUser());

            return;
        }

        $this->suggestionApplier->reject($suggestion, $this->getAdminUser());
    }

    private function assertCsrfToken(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_csrf_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    /** Back to the page that posted the form ("_redirect", same host paths only), or to the grid. */
    private function redirectBack(Request $request): RedirectResponse
    {
        $redirect = $request->request->getString('_redirect');
        if (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//') && !str_contains($redirect, '\\')) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('tmonier_ai_copilot_admin_ai_suggestion_index');
    }

    private function getAdminUser(): ?AdminUserInterface
    {
        $user = $this->getUser();

        return $user instanceof AdminUserInterface ? $user : null;
    }
}
