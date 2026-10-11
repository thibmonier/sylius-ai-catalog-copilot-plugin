<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Facts;

/**
 * The only facts a prompt may use about a product: catalog data, never customer data.
 */
final class ProductFacts
{
    /**
     * @param list<string> $taxons main taxon first
     * @param list<array{name: string, value: string, code?: string}> $attributes
     * @param list<array{name: string, values: list<string>}> $options
     */
    public function __construct(
        public readonly string $localeCode,
        public readonly string $code,
        public readonly string $name,
        public readonly array $taxons = [],
        public readonly array $attributes = [],
        public readonly array $options = [],
    ) {
    }
}
