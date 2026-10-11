<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Facts;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslation;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeTranslation;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Product\Model\ProductOption;
use Sylius\Component\Product\Model\ProductOptionTranslation;
use Sylius\Component\Product\Model\ProductOptionValue;
use Sylius\Component\Product\Model\ProductOptionValueTranslation;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\CatalogTrait;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractor;

final class ProductFactsExtractorTest extends TestCase
{
    use CatalogTrait;

    public function testOptionsListOnlyTheValuesOfTheEnabledVariants(): void
    {
        $product = $this->product();
        $size = $this->option('size', 'Size', ['XS', 'S', 'M', 'L', 'XL']);
        $product->addOption($size);
        $this->variant($product, $size, ['M'], true);
        $this->variant($product, $size, ['S'], true);
        $this->variant($product, $size, ['XL'], false);

        $facts = (new ProductFactsExtractor())->extract($product, 'en_US', 'en_US');

        // Option order, not variant order; disabled variants and unused values excluded.
        self::assertSame([['name' => 'Size', 'values' => ['S', 'M']]], $facts->options);
    }

    public function testAttributesOfTheTargetLocaleOnlyAreIncludedAndPreferred(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt'], 'fr_FR' => ['name' => 'Chemise en lin']]);
        $this->attributeValue($product, 'material', ['en_US' => 'Material', 'fr_FR' => 'Matière'], 'fr_FR', 'Lin');
        $this->attributeValue($product, 'fit', ['en_US' => 'Fit'], 'fr_FR', 'Ajustée');
        $this->attributeValue($product, 'colour', ['en_US' => 'Colour', 'fr_FR' => 'Couleur'], 'en_US', 'Blue');
        $this->attributeValue($product, 'colour', ['en_US' => 'Colour', 'fr_FR' => 'Couleur'], 'fr_FR', 'Bleu');
        $this->attributeValue($product, 'collar', ['en_US' => 'Collar'], 'en_US', 'Mandarin');
        $this->attributeValue($product, 'origin', ['de_DE' => 'Herkunft'], 'de_DE', 'Portugal');

        $facts = (new ProductFactsExtractor())->extract($product, 'fr_FR', 'en_US');

        self::assertSame('Chemise en lin', $facts->name);
        self::assertSame([
            ['name' => 'Collar', 'value' => 'Mandarin', 'code' => 'collar'],
            ['name' => 'Couleur', 'value' => 'Bleu', 'code' => 'colour'],
            ['name' => 'Fit', 'value' => 'Ajustée', 'code' => 'fit'],
            ['name' => 'Matière', 'value' => 'Lin', 'code' => 'material'],
        ], $facts->attributes);
    }

    public function testNoTranslationIsCreatedOnTaxonsOptionsOrAttributes(): void
    {
        $product = $this->product(['en_US' => ['name' => 'Linen shirt'], 'de_DE' => ['name' => 'Leinenhemd']]);
        $taxon = new Taxon();
        $taxon->setCode('SHIRTS');
        $taxon->setCurrentLocale('fr_FR');
        $taxon->setFallbackLocale('fr_FR');
        $taxonTranslation = new TaxonTranslation();
        $taxonTranslation->setLocale('fr_FR');
        $taxonTranslation->setName('Chemises');
        $taxon->addTranslation($taxonTranslation);
        $product->setMainTaxon($taxon);
        $size = $this->option('size', 'Taille', ['M'], 'fr_FR');
        $product->addOption($size);
        $this->variant($product, $size, ['M'], true);
        $attribute = $this->attributeValue($product, 'material', ['fr_FR' => 'Matière'], null, 'Lin');

        $facts = (new ProductFactsExtractor())->extract($product, 'de_DE', 'en_US');

        self::assertSame([], $facts->taxons);
        self::assertSame([], $facts->options);
        self::assertSame([], $facts->attributes);
        self::assertSame(['fr_FR'], $taxon->getTranslations()->getKeys());
        self::assertSame(['fr_FR'], $size->getTranslations()->getKeys());
        self::assertSame(['fr_FR'], $attribute->getTranslations()->getKeys());
        foreach ($size->getValues() as $value) {
            self::assertSame(['fr_FR'], $value->getTranslations()->getKeys());
        }
    }

    /** @param list<string> $values */
    private function option(string $code, string $name, array $values, string $locale = 'en_US'): ProductOption
    {
        $option = new ProductOption();
        $option->setCode($code);
        $translation = new ProductOptionTranslation();
        $translation->setLocale($locale);
        $translation->setName($name);
        $option->addTranslation($translation);
        foreach ($values as $value) {
            $optionValue = new ProductOptionValue();
            $optionValue->setCode($code . '_' . $value);
            $valueTranslation = new ProductOptionValueTranslation();
            $valueTranslation->setLocale($locale);
            $valueTranslation->setValue($value);
            $optionValue->addTranslation($valueTranslation);
            $option->addValue($optionValue);
        }

        return $option;
    }

    /** @param list<string> $values */
    private function variant(ProductInterface $product, ProductOption $option, array $values, bool $enabled): void
    {
        $variant = new ProductVariant();
        $variant->setCode($product->getCode() . '_' . implode('_', $values));
        $variant->setEnabled($enabled);
        foreach ($option->getValues() as $optionValue) {
            if (in_array(substr((string) $optionValue->getCode(), strlen((string) $option->getCode()) + 1), $values, true)) {
                $variant->addOptionValue($optionValue);
            }
        }
        $product->addVariant($variant);
    }

    /** @param array<string, string> $names */
    private function attributeValue(ProductInterface $product, string $code, array $names, ?string $locale, string $value): ProductAttribute
    {
        $attribute = null;
        foreach ($product->getAttributes() as $existing) {
            if ($existing->getAttribute()?->getCode() === $code && $existing->getAttribute() instanceof ProductAttribute) {
                $attribute = $existing->getAttribute();
            }
        }
        if (null === $attribute) {
            $attribute = new ProductAttribute();
            $attribute->setCode($code);
            $attribute->setType('text');
            $attribute->setStorageType('text');
            foreach ($names as $nameLocale => $name) {
                $translation = new ProductAttributeTranslation();
                $translation->setLocale($nameLocale);
                $translation->setName($name);
                $attribute->addTranslation($translation);
            }
        }

        $attributeValue = new ProductAttributeValue();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode($locale);
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);

        return $attribute;
    }
}
