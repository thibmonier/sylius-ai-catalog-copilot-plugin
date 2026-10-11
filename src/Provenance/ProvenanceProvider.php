<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Provenance;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepositoryInterface;

/**
 * Reads the provenance of product texts. Results are cached per request, as the shop may
 * ask for several fields of the same product.
 */
final class ProvenanceProvider implements ProvenanceProviderInterface
{
    /** @var array<string, array<string, ProvenanceView>> */
    private array $cache = [];

    public function __construct(private readonly AiProvenanceRepositoryInterface $provenanceRepository)
    {
    }

    public function get(ProductInterface $product, string $field, ?string $localeCode = null): ?ProvenanceView
    {
        return $this->all($product, $localeCode)[$field] ?? null;
    }

    public function all(ProductInterface $product, ?string $localeCode = null): array
    {
        $translation = null === $localeCode ? $product->getTranslation() : $product->getTranslations()->get($localeCode);
        if (!$translation instanceof ProductTranslationInterface || null === $translation->getId()) {
            return [];
        }

        $key = (string) $translation->getId();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $views = [];
        foreach ($this->provenanceRepository->findByTranslation($translation) as $field => $provenance) {
            if (!ProductField::isValid($field)) {
                continue;
            }

            $views[$field] = new ProvenanceView(
                $field,
                (string) $translation->getLocale(),
                $provenance->matches(ProductField::getValue($translation, $field)) ? ProvenanceView::STATUS_GENERATED : ProvenanceView::STATUS_EDITED,
                $provenance->getGenerator(),
                $provenance->getModel(),
                $provenance->getGeneratedAt(),
                $provenance->getAcceptedAt(),
            );
        }

        return $this->cache[$key] = $views;
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
