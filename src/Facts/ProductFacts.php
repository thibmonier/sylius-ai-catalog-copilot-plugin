<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Facts;

/**
 * The only facts a prompt may use about a product: catalog data, never customer data.
 */
final readonly class ProductFacts
{
    /**
     * @param list<string> $taxons main taxon first
     * @param list<array{name: string, value: string, code?: string}> $attributes
     * @param list<array{name: string, values: list<string>}> $options
     */
    public function __construct(
        public string $localeCode,
        public string $code,
        public string $name,
        public array $taxons = [],
        public array $attributes = [],
        public array $options = [],
    ) {
    }
}
