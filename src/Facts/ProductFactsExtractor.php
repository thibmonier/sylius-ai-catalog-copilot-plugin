<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Facts;

use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * Reads the catalog facts of a product. It never calls getTranslation(): on a missing locale,
 * that method creates and attaches an empty translation, which the next flush would insert.
 */
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
            $this->translated($product, 'getName', $localeCode, $fallbackLocaleCode) ?? (string) $product->getCode(),
            $this->taxons($product, $localeCode, $fallbackLocaleCode),
            $this->attributes($product, $localeCode, $fallbackLocaleCode),
            $this->options($product, $localeCode, $fallbackLocaleCode),
        );
    }

    /** @return list<string> */
    private function taxons(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): array
    {
        $taxons = [];
        $mainTaxon = $product->getMainTaxon();
        if ($mainTaxon instanceof TaxonInterface) {
            $taxons[] = $this->translated($mainTaxon, 'getName', $localeCode, $fallbackLocaleCode);
        }

        foreach ($product->getTaxons() as $taxon) {
            $taxons[] = $this->translated($taxon, 'getName', $localeCode, $fallbackLocaleCode);
        }

        return array_values(array_unique(array_filter($taxons, static fn (?string $name): bool => null !== $name)));
    }

    /**
     * One value per attribute: the value in $localeCode, else in $fallbackLocaleCode, else the
     * non-translatable value. Attributes set only in $localeCode are included.
     *
     * @return list<array{name: string, value: string, code: string}>
     */
    private function attributes(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): array
    {
        $rank = [$localeCode => 0, $fallbackLocaleCode => 1, '' => 2];
        /** @var array<string, array{0: int, 1: AttributeValueInterface}> $chosen */
        $chosen = [];
        /** @var AttributeValueInterface $attributeValue */
        foreach ($product->getAttributes() as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            $code = $attribute?->getCode();
            $valueRank = $rank[$attributeValue->getLocaleCode() ?? ''] ?? null;
            if (null === $attribute || null === $code || null === $valueRank) {
                continue;
            }
            if (!isset($chosen[$code]) || $valueRank < $chosen[$code][0]) {
                $chosen[$code] = [$valueRank, $attributeValue];
            }
        }

        $attributes = [];
        foreach ($chosen as $code => [, $attributeValue]) {
            $attribute = $attributeValue->getAttribute();
            $name = null === $attribute ? null : $this->translated($attribute, 'getName', $localeCode, $fallbackLocaleCode);
            $value = $this->attributeValue($attributeValue, $localeCode, $fallbackLocaleCode);
            if (null === $name || '' === $value) {
                continue;
            }

            $attributes[] = ['name' => $name, 'value' => mb_substr($value, 0, $this->maxAttributeLength), 'code' => (string) $code];
        }

        usort($attributes, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $attributes;
    }

    private function attributeValue(AttributeValueInterface $attributeValue, string $localeCode, string $fallbackLocaleCode): string
    {
        $value = $attributeValue->getValue();

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return implode(', ', $this->choiceLabels($attributeValue, $value, $localeCode, $fallbackLocaleCode));
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
    private function choiceLabels(AttributeValueInterface $attributeValue, array $keys, string $localeCode, string $fallbackLocaleCode): array
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
                $translated = $choice[$localeCode] ?? $choice[$fallbackLocaleCode] ?? reset($choice);
                $label = is_scalar($translated) ? (string) $translated : $label;
            }
            $labels[] = trim($label);
        }

        return $labels;
    }

    /**
     * Options with the values used by the enabled variants only (not every value of a shared
     * option), in the order of the product options.
     *
     * @return list<array{name: string, values: list<string>}>
     */
    private function options(ProductInterface $product, string $localeCode, string $fallbackLocaleCode): array
    {
        /** @var array<string, array<string, string>> $usedValues option code => value code => label */
        $usedValues = [];
        /** @var ProductVariantInterface $variant */
        foreach ($product->getEnabledVariants() as $variant) {
            /** @var ProductOptionValueInterface $optionValue */
            foreach ($variant->getOptionValues() as $optionValue) {
                $optionCode = $optionValue->getOption()?->getCode();
                $label = $this->translated($optionValue, 'getValue', $localeCode, $fallbackLocaleCode);
                if (null !== $optionCode && null !== $label) {
                    $usedValues[$optionCode][(string) $optionValue->getCode()] = $label;
                }
            }
        }

        $options = [];
        /** @var ProductOptionInterface $option */
        foreach ($product->getOptions() as $option) {
            $values = $usedValues[(string) $option->getCode()] ?? [];
            $name = $this->translated($option, 'getName', $localeCode, $fallbackLocaleCode);
            if (null === $name || [] === $values) {
                continue;
            }

            $ordered = [];
            /** @var ProductOptionValueInterface $optionValue */
            foreach ($option->getValues() as $optionValue) {
                $valueCode = (string) $optionValue->getCode();
                if (isset($values[$valueCode])) {
                    $ordered[] = $values[$valueCode];
                }
            }
            $options[] = ['name' => $name, 'values' => $ordered];
        }

        return $options;
    }

    /**
     * A text of a translation without creating any translation: $localeCode, then
     * $fallbackLocaleCode, then null.
     *
     * @param TranslatableInterface<\Sylius\Resource\Model\TranslationInterface> $translatable
     */
    private function translated(TranslatableInterface $translatable, string $getter, string $localeCode, string $fallbackLocaleCode): ?string
    {
        foreach ([$localeCode, $fallbackLocaleCode] as $locale) {
            $translation = $translatable->getTranslations()->get($locale);
            if (null === $translation || !method_exists($translation, $getter)) {
                continue;
            }

            $text = $translation->{$getter}();
            if (is_string($text) && '' !== trim($text)) {
                return trim($text);
            }
        }

        return null;
    }
}
