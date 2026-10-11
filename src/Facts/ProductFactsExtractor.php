<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Facts;

use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

final class ProductFactsExtractor implements ProductFactsExtractorInterface
{
    public function __construct(private readonly int $maxAttributeLength = 500)
    {
    }

    public function extract(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): ProductFacts
    {
        return new ProductFacts(
            $localeCode,
            (string) $product->getCode(),
            $this->productName($product, $localeCode, $fallbackLocaleCode),
            $this->taxons($product, $localeCode),
            $this->attributes($product, $localeCode, $fallbackLocaleCode),
            $this->options($product, $localeCode),
        );
    }

    private function productName(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): string
    {
        foreach ([$localeCode, $fallbackLocaleCode] as $locale) {
            $translation = $product->getTranslations()->get($locale);
            $name = $translation instanceof ProductTranslationInterface ? trim((string) $translation->getName()) : '';
            if ('' !== $name) {
                return $name;
            }
        }

        return (string) $product->getCode();
    }

    /** @return list<string> */
    private function taxons(ProductInterface $product, string $localeCode): array
    {
        $taxons = [];
        $mainTaxon = $product->getMainTaxon();
        if ($mainTaxon instanceof TaxonInterface) {
            $taxons[] = $this->taxonName($mainTaxon, $localeCode);
        }

        foreach ($product->getTaxons() as $taxon) {
            $taxons[] = $this->taxonName($taxon, $localeCode);
        }

        return array_values(array_unique(array_filter($taxons, static fn (string $name): bool => '' !== $name)));
    }

    private function taxonName(TaxonInterface $taxon, string $localeCode): string
    {
        return trim((string) $taxon->getTranslation($localeCode)->getName());
    }

    /** @return list<array{name: string, value: string}> */
    private function attributes(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): array
    {
        $attributes = [];
        /** @var AttributeValueInterface $attributeValue */
        foreach ($product->getAttributesByLocale($localeCode, $fallbackLocaleCode) as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            if (null === $attribute) {
                continue;
            }

            $name = trim((string) $attribute->getTranslation($localeCode)->getName());
            $value = $this->attributeValue($attributeValue, $localeCode);
            if ('' === $name || '' === $value) {
                continue;
            }

            $attributes[] = ['name' => $name, 'value' => mb_substr($value, 0, $this->maxAttributeLength)];
        }

        usort($attributes, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $attributes;
    }

    private function attributeValue(AttributeValueInterface $attributeValue, string $localeCode): string
    {
        $value = $attributeValue->getValue();

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return implode(', ', $this->choiceLabels($attributeValue, $value, $localeCode));
        }
        if (is_scalar($value)) {
            return trim(strip_tags((string) $value));
        }

        return '';
    }

    /**
     * Select attributes store choice keys: translate them with the attribute configuration.
     *
     * @param array<mixed> $keys
     *
     * @return list<string>
     */
    private function choiceLabels(AttributeValueInterface $attributeValue, array $keys, string $localeCode): array
    {
        $configuration = $attributeValue->getAttribute()?->getConfiguration() ?? [];
        $choices = is_array($configuration['choices'] ?? null) ? $configuration['choices'] : [];

        $labels = [];
        foreach ($keys as $key) {
            if (!is_int($key) && !is_string($key)) {
                continue;
            }

            $choice = $choices[$key] ?? null;
            $label = (string) $key;
            if (is_array($choice) && [] !== $choice) {
                $translated = $choice[$localeCode] ?? reset($choice);
                $label = is_scalar($translated) ? (string) $translated : $label;
            }
            $labels[] = trim($label);
        }

        return $labels;
    }

    /** @return list<array{name: string, values: list<string>}> */
    private function options(ProductInterface $product, string $localeCode): array
    {
        $options = [];
        /** @var ProductOptionInterface $option */
        foreach ($product->getOptions() as $option) {
            $values = [];
            /** @var ProductOptionValueInterface $optionValue */
            foreach ($option->getValues() as $optionValue) {
                $values[] = trim((string) $optionValue->getTranslation($localeCode)->getValue());
            }

            $name = trim((string) $option->getTranslation($localeCode)->getName());
            if ('' !== $name) {
                $options[] = ['name' => $name, 'values' => array_values(array_filter($values, static fn (string $value): bool => '' !== $value))];
            }
        }

        return $options;
    }
}
