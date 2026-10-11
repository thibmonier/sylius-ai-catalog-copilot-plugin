<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Provenance;

use Sylius\Component\Core\Model\ProductInterface;

interface ProvenanceProviderInterface
{
    /** Provenance of one field, null when it was never written from an AI suggestion. */
    public function get(ProductInterface $product, string $field, ?string $localeCode = null): ?ProvenanceView;

    /** @return array<string, ProvenanceView> indexed by field */
    public function all(ProductInterface $product, ?string $localeCode = null): array;
}
