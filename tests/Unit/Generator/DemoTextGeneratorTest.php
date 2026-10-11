<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationFailedException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;
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

        self::assertSame('Linen Shirt (Colour: Blue, Material: Linen).', $generator->generate($this->request(ProductField::SHORT_DESCRIPTION))->text);
        self::assertSame('Linen Shirt - Shirts. Colour: Blue. Material: Linen. Order online today.', $generator->generate($this->request(ProductField::META_DESCRIPTION))->text);
        self::assertSame('linen shirt, shirts, blue, linen', $generator->generate($this->request(ProductField::META_KEYWORDS))->text);
        self::assertSame('demo', $generator->getType());
    }

    /** @return iterable<string, array{string, non-empty-string, non-empty-string, string}> locale, description start, meta description end, claims */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr_FR', 'Linen Shirt fait partie de notre gamme Shirts.', 'Commandez en ligne dès aujourd\'hui.', 'Un choix écologique pour tous les jours. Livraison neutre en carbone.'];
        yield 'de' => ['de_DE', 'Linen Shirt gehört zu unserer Kollektion Shirts.', 'Jetzt online bestellen.', 'Eine umweltfreundliche Wahl für jeden Tag. Klimaneutral versendet.'];
        yield 'es' => ['es_ES', 'Linen Shirt forma parte de nuestra gama Shirts.', 'Pídalo hoy en línea.', 'Una opción ecológica para cada día. Envío neutro en carbono.'];
        yield 'it' => ['it_IT', 'Linen Shirt fa parte della nostra gamma Shirts.', 'Ordina online oggi.', 'Una scelta ecologica per tutti i giorni. Spedizione a impatto zero.'];
        yield 'nl' => ['nl_NL', 'Linen Shirt maakt deel uit van onze collectie Shirts.', 'Bestel vandaag online.', 'Een milieuvriendelijke keuze voor elke dag. Klimaatneutraal verzonden.'];
        yield 'pl' => ['pl_PL', 'Linen Shirt należy do naszej kolekcji Shirts.', 'Zamów online już dziś.', 'Ekologiczny wybór na co dzień. Wysyłka neutralna klimatycznie.'];
        yield 'unknown locale falls back to English' => ['pt_PT', 'Linen Shirt is part of our Shirts range.', 'Order online today.', 'An eco-friendly choice for every day. Shipped carbon neutral.'];
    }

    /**
     * @param non-empty-string $start
     * @param non-empty-string $end
     */
    #[DataProvider('locales')]
    public function testItWritesWithTheTemplatesOfTheTargetLocale(string $locale, string $start, string $end, string $claims): void
    {
        $generator = new DemoTextGenerator();
        $facts = $this->facts($locale);

        self::assertStringStartsWith($start, $generator->generate(new GenerationRequest(ProductField::DESCRIPTION, $facts, new Prompt('s', 'u')))->text);
        self::assertStringEndsWith($end, $generator->generate(new GenerationRequest(ProductField::META_DESCRIPTION, $facts, new Prompt('s', 'u')))->text);
        self::assertSame($claims, DemoTextGenerator::templates($locale)['claims']);
        // The demo claims exercise the guard in every language: one generic claim, one neutrality claim.
        $analysis = (new EnvironmentalClaimDetector())->analyse($claims, $facts);
        self::assertSame(['generic', 'carbon_neutral'], array_column($analysis['unsubstantiated'], 'type'));
    }

    public function testATranslationIsRebuiltFromTheTargetFactsWithoutLocalePrefix(): void
    {
        $request = new GenerationRequest(ProductField::DESCRIPTION, $this->facts('fr_FR'), new Prompt('s', 'u'), 'en_US', 'A linen shirt is part of our range.');

        $text = (new DemoTextGenerator())->generate($request)->text;

        self::assertStringStartsWith('Linen Shirt fait partie de notre gamme Shirts.', $text);
        self::assertStringNotContainsString('[fr_FR]', $text);
        self::assertStringNotContainsString('A linen shirt is part of our range.', $text);
    }

    public function testOneProductOutOfThreeGetsAnEnvironmentalClaimToDemonstrateTheGuard(): void
    {
        $withClaim = 0;
        foreach (range(1, 30) as $index) {
            $facts = new ProductFacts('en_US', 'CODE_' . $index, 'Product');
            $text = (new DemoTextGenerator())->generate(new GenerationRequest(ProductField::DESCRIPTION, $facts, new Prompt('s', 'u')))->text;
            $withClaim += str_contains($text, DemoTextGenerator::templates('en_US')['claims']) ? 1 : 0;
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
