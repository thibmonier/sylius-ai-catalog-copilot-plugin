<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Event\AiSuggestionAccepted;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepositoryInterface;

final class SuggestionApplier implements SuggestionApplierInterface
{
    /**
     * @param FactoryInterface<AiProvenanceInterface> $provenanceFactory
     */
    public function __construct(
        private readonly AiProvenanceRepositoryInterface $provenanceRepository,
        private readonly FactoryInterface $provenanceFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly bool $recordProvenance = true,
    ) {
    }

    public function accept(AiSuggestionInterface $suggestion, ?AdminUserInterface $decidedBy): void
    {
        $this->assertPending($suggestion);

        $product = $suggestion->getProduct();
        $translation = $product?->getTranslations()->get($suggestion->getLocaleCode());
        if (!$product instanceof ProductInterface || !$translation instanceof ProductTranslationInterface) {
            throw new SuggestionException('tmonier_ai_copilot.error.missing_translation', ['%locale%' => $suggestion->getLocaleCode()]);
        }

        $previousText = ProductField::getValue($translation, $suggestion->getField());
        if (self::normalize($previousText) !== self::normalize($suggestion->getOriginalText())) {
            throw new SuggestionException('tmonier_ai_copilot.error.stale');
        }

        $now = $this->clock->now();
        ProductField::setValue($translation, $suggestion->getField(), $suggestion->getSuggestedText());
        $suggestion->accept($decidedBy, $now);

        if ($this->recordProvenance) {
            $provenance = $this->provenanceRepository->findOneByTranslationAndField($translation, $suggestion->getField());
            if (null === $provenance) {
                $provenance = $this->provenanceFactory->createNew();
                $provenance->setTranslation($translation);
                $this->entityManager->persist($provenance);
            }
            $provenance->record($suggestion, $suggestion->getSuggestedText(), $now);
        }

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new AiSuggestionAccepted(
            $suggestion,
            $product,
            $previousText,
            $suggestion->getSuggestedText(),
            $decidedBy,
        ));
    }

    public function reject(AiSuggestionInterface $suggestion, ?AdminUserInterface $decidedBy): void
    {
        $this->assertPending($suggestion);

        $suggestion->reject($decidedBy, $this->clock->now());
        $this->entityManager->flush();
    }

    private function assertPending(AiSuggestionInterface $suggestion): void
    {
        if (!$suggestion->isPending()) {
            throw new SuggestionException('tmonier_ai_copilot.error.already_decided');
        }
    }

    private static function normalize(?string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
    }
}
