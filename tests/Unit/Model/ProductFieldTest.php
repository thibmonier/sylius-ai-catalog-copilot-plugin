<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductTranslation;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

final class ProductFieldTest extends TestCase
{
    public function testItReadsAndWritesEveryField(): void
    {
        $translation = new ProductTranslation();
        foreach (ProductField::ALL as $field) {
            ProductField::setValue($translation, $field, 'value of ' . $field);
        }

        self::assertSame('value of description', $translation->getDescription());
        self::assertSame('value of short_description', $translation->getShortDescription());
        self::assertSame('value of meta_description', $translation->getMetaDescription());
        self::assertSame('value of meta_keywords', $translation->getMetaKeywords());
        foreach (ProductField::ALL as $field) {
            self::assertSame('value of ' . $field, ProductField::getValue($translation, $field));
            self::assertTrue(ProductField::isValid($field));
        }
        self::assertSame('metaKeywords', ProductField::property(ProductField::META_KEYWORDS));
    }

    public function testOnlyCopilotFieldsAreValid(): void
    {
        self::assertFalse(ProductField::isValid('name'));
        self::assertFalse(ProductField::isValid('slug'));
        self::assertTrue(ProductField::isSingleLine(ProductField::META_DESCRIPTION));
        self::assertFalse(ProductField::isSingleLine(ProductField::DESCRIPTION));

        $this->expectException(\InvalidArgumentException::class);
        ProductField::getValue(new ProductTranslation(), 'name');
    }
}
