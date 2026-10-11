<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Guard;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

final class FieldLimitsTest extends TestCase
{
    public function testDefaults(): void
    {
        $limits = new FieldLimits();

        self::assertSame(50, $limits->minLength(ProductField::META_DESCRIPTION));
        self::assertSame(160, $limits->maxLength(ProductField::META_DESCRIPTION));
        self::assertSame(255, $limits->maxLength(ProductField::META_KEYWORDS));
        self::assertSame(0, $limits->minLength(ProductField::DESCRIPTION));
    }

    public function testOverridesAreMergedAndMetaFieldsNeverExceedTheirColumn(): void
    {
        $limits = new FieldLimits([
            ProductField::META_DESCRIPTION => ['max' => 400],
            ProductField::SHORT_DESCRIPTION => ['min' => 20],
            'unknown' => ['max' => 1],
        ]);

        self::assertSame(255, $limits->maxLength(ProductField::META_DESCRIPTION));
        self::assertSame(50, $limits->minLength(ProductField::META_DESCRIPTION));
        self::assertSame(20, $limits->minLength(ProductField::SHORT_DESCRIPTION));
        self::assertSame(1000, $limits->maxLength(ProductField::SHORT_DESCRIPTION));
    }
}
