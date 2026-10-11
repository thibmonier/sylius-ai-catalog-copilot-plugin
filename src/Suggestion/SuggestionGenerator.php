<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractorInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationFailedException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformUnavailableException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\TextGeneratorInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuardInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\PromptBuilderInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;

final class SuggestionGenerator implements SuggestionGeneratorInterface
{
    /**
     * @param FactoryInterface<AiSuggestionInterface> $suggestionFactory
     */
    public function __construct(
        private readonly ProductFactsExtractorInterface $factsExtractor,
        private readonly PromptBuilderInterface $promptBuilder,
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ContentGuardInterface $contentGuard,
        private readonly FactoryInterface $suggestionFactory,
        private readonly AiSuggestionRepositoryInterface $suggestionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $defaultLocaleCode,
    ) {
    }

    public function generate(
        ProductInterface $product,
        string $field,
        string $localeCode,
        ?string $sourceLocaleCode = null,
        ?AdminUserInterface $requestedBy = null,
    ): AiSuggestionInterface {
        if (!ProductField::isValid($field)) {
            throw new SuggestionException('tmonier_ai_copilot.error.unknown_field', ['%field%' => $field]);
        }
        if ($sourceLocaleCode === $localeCode) {
            throw new SuggestionException('tmonier_ai_copilot.error.same_locale');
        }

        $facts = $this->factsExtractor->extract($product, $localeCode, $sourceLocaleCode ?? $this->defaultLocaleCode);
        $sourceText = null;
        if (null !== $sourceLocaleCode) {
            $sourceText = $this->fieldValue($product, $sourceLocaleCode, $field);
            if (null === $sourceText || '' === trim(html_entity_decode(strip_tags($sourceText), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))) {
                throw new SuggestionException('tmonier_ai_copilot.error.empty_source', ['%locale%' => $sourceLocaleCode]);
            }
            $prompt = $this->promptBuilder->buildTranslation($field, $facts, $sourceLocaleCode, $sourceText);
        } else {
            $prompt = $this->promptBuilder->buildGeneration($field, $facts);
        }

        $request = new GenerationRequest($field, $facts, $prompt, $sourceLocaleCode, $sourceText);

        try {
            $generated = $this->textGenerator->generate($request);
        } catch (GenerationFailedException $exception) {
            $this->logger->error('AI suggestion generation failed for product {product}, field {field}, locale {locale}: {message}', [
                'product' => $product->getCode(),
                'field' => $field,
                'locale' => $localeCode,
                'message' => $exception->getMessage(),
                // No exception object: stack trace arguments could hold request headers (API key).
                'exception_class' => ($exception->getPrevious() ?? $exception)::class,
            ]);

            throw new SuggestionException($exception instanceof PlatformUnavailableException ? 'tmonier_ai_copilot.error.platform_unavailable' : 'tmonier_ai_copilot.error.generation_failed', [], $exception);
        }

        $guarded = $this->contentGuard->apply($field, $generated->text, $facts);
        if ('' === $guarded->text) {
            throw new SuggestionException('tmonier_ai_copilot.error.empty_result');
        }

        $now = $this->clock->now();
        foreach ($this->suggestionRepository->findPendingFor($product, $localeCode, $field) as $previous) {
            $previous->reject(null, $now);
        }

        $suggestion = $this->suggestionFactory->createNew();
        $suggestion->setProduct($product);
        $suggestion->setField($field);
        $suggestion->setLocaleCode($localeCode);
        $suggestion->setSourceLocaleCode($sourceLocaleCode);
        $suggestion->setOriginalText($this->fieldValue($product, $localeCode, $field));
        $suggestion->setSuggestedText($guarded->text);
        $suggestion->setWarnings($guarded->warnings);
        $suggestion->setGenerator($this->textGenerator->getType());
        $suggestion->setModel($generated->model);
        $suggestion->setPromptHash($prompt->hash());
        $suggestion->setTokens($generated->inputTokens, $generated->outputTokens);
        $suggestion->setRequestedBy($requestedBy);
        $suggestion->setCreatedAt($now);

        $this->entityManager->persist($suggestion);
        $this->entityManager->flush();

        return $suggestion;
    }

    private function fieldValue(ProductInterface $product, string $localeCode, string $field): ?string
    {
        $translation = $product->getTranslations()->get($localeCode);

        return $translation instanceof ProductTranslationInterface ? ProductField::getValue($translation, $field) : null;
    }
}
