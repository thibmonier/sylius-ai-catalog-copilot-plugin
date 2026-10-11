<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationFailedException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\Prompt;

final class DemoTextGeneratorTest extends TestCase
{
    public function testItWritesEveryFieldFromTheFactsDeterministically(): void
    {
        $generator = new DemoTextGenerator();

        $description = $generator->generate($this->request(ProductField::DESCRIPTION));
        self::assertSame('demo', $description->model);
        self::assertStringStartsWith("Linen Shirt is part of our Shirts range.\n\nColour: Blue.\nMaterial: Linen.\nAvailable size: S, M.", $description->text);
        self::assertEquals($description, $generator->generate($this->request(ProductField::DESCRIPTION)));

        self::assertSame('Linen Shirt, colour Blue, material Linen.', $generator->generate($this->request(ProductField::SHORT_DESCRIPTION))->text);
        self::assertSame('Linen Shirt - Shirts. Colour: Blue. Material: Linen. Order online today.', $generator->generate($this->request(ProductField::META_DESCRIPTION))->text);
        self::assertSame('linen shirt, shirts, blue, linen', $generator->generate($this->request(ProductField::META_KEYWORDS))->text);
        self::assertSame('demo', $generator->getType());
    }

    public function testTranslationsArePrefixedWithTheTargetLocale(): void
    {
        $request = new GenerationRequest(ProductField::DESCRIPTION, $this->facts('fr_FR'), new Prompt('s', 'u'), 'en_US', 'A linen shirt.');

        self::assertSame('[fr_FR] A linen shirt.', (new DemoTextGenerator())->generate($request)->text);
    }

    public function testOneProductOutOfThreeGetsAnEnvironmentalClaimToDemonstrateTheGuard(): void
    {
        $withClaim = 0;
        foreach (range(1, 30) as $index) {
            $facts = new ProductFacts('en_US', 'CODE_' . $index, 'Product');
            $text = (new DemoTextGenerator())->generate(new GenerationRequest(ProductField::DESCRIPTION, $facts, new Prompt('s', 'u')))->text;
            $withClaim += str_contains($text, 'An eco-friendly choice for every day. Shipped carbon neutral.') ? 1 : 0;
        }

        self::assertGreaterThan(0, $withClaim);
        self::assertLessThan(30, $withClaim);
    }

    public function testUnknownFieldsFail(): void
    {
        $this->expectException(GenerationFailedException::class);

        (new DemoTextGenerator())->generate($this->request('name'));
    }

    private function request(string $field): GenerationRequest
    {
        return new GenerationRequest($field, $this->facts('en_US'), new Prompt('s', 'u'));
    }

    private function facts(string $locale): ProductFacts
    {
        return new ProductFacts(
            $locale,
            'LINEN_SHIRT',
            'Linen Shirt',
            ['Shirts'],
            [['name' => 'Colour', 'value' => 'Blue'], ['name' => 'Material', 'value' => 'Linen']],
            [['name' => 'Size', 'values' => ['S', 'M']]],
        );
    }
}
