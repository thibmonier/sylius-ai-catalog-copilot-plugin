<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Facts;

use Sylius\Component\Core\Model\ProductInterface;

interface ProductFactsExtractorInterface
{
    /**
     * @param string $localeCode locale of the texts to read (names, attribute values)
     * @param string $fallbackLocaleCode locale used when a text is missing in $localeCode
     */
    public function extract(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): ProductFacts;
}
