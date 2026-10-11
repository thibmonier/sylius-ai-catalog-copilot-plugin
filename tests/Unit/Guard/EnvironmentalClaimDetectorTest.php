<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Guard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;

final class EnvironmentalClaimDetectorTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function claims(): iterable
    {
        yield 'en eco-friendly' => ['A truly Eco-Friendly shirt.', 'Eco-Friendly', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'en without hyphen' => ['An eco friendly shirt.', 'eco friendly', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'en sustainable' => ['Made to be sustainable.', 'sustainable', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'en green choice' => ['The green choice for you.', 'green choice', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'fr écologique' => ['Un t-shirt écologique.', 'écologique', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'fr éco-responsable' => ['Un choix éco-responsable.', 'éco-responsable', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'de umweltfreundlich' => ['Ein umweltfreundliches Produkt.', 'umweltfreundliches', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'es sostenible' => ['Una camiseta sostenible.', 'sostenible', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'it ecosostenibile' => ['Una maglietta ecosostenibile.', 'ecosostenibile', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'nl duurzaam' => ['Een duurzaam shirt.', 'duurzaam', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'pl ekologiczna' => ['Ekologiczna koszulka.', 'Ekologiczna', EnvironmentalClaimDetector::TYPE_GENERIC];
        yield 'en biodegradable' => ['The bag is biodegradable.', 'biodegradable', EnvironmentalClaimDetector::TYPE_BIODEGRADABLE];
        yield 'de biologisch abbaubar' => ['Die Tüte ist biologisch abbaubar.', 'biologisch abbaubar', EnvironmentalClaimDetector::TYPE_BIODEGRADABLE];
        yield 'en carbon neutral' => ['A carbon-neutral delivery.', 'carbon-neutral', EnvironmentalClaimDetector::TYPE_CARBON_NEUTRAL];
        yield 'fr neutre en carbone' => ['Livraison neutre en carbone.', 'neutre en carbone', EnvironmentalClaimDetector::TYPE_CARBON_NEUTRAL];
        yield 'de klimaneutral' => ['Klimaneutral hergestellt.', 'Klimaneutral', EnvironmentalClaimDetector::TYPE_CARBON_NEUTRAL];
    }

    #[DataProvider('claims')]
    public function testItFindsGenericClaimsInEveryLanguage(string $text, string $claim, string $type): void
    {
        self::assertSame([['claim' => $claim, 'type' => $type]], (new EnvironmentalClaimDetector())->findUnsubstantiated($text, ''));
    }

    public function testItMatchesWholeWordsOnly(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertSame([], $detector->findUnsubstantiated('A green shirt, unsustainableness, greenery, ecologist.', ''));
        self::assertSame([], $detector->findUnsubstantiated('Colour: green. Ecolabel-free text.', ''));
    }

    public function testARecognisedEcolabelAttributeSubstantiatesGenericClaims(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertSame([], $detector->findUnsubstantiated('An eco-friendly shirt.', 'certification: eu ecolabel (fr/016/012)'));
        self::assertCount(1, $detector->findUnsubstantiated('An eco-friendly shirt.', 'material: organic cotton'));
    }

    public function testExtraRecognisedLabelsCanBeConfigured(): void
    {
        $detector = new EnvironmentalClaimDetector(['My National Label']);

        self::assertSame([], $detector->findUnsubstantiated('Ein nachhaltiges Hemd.', 'label: my national label'));
    }

    public function testBiodegradableNeedsTheSameProperty(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertSame([], $detector->findUnsubstantiated('The bag is biodegradable.', 'compostability: biodegradable according to en 13432'));
        self::assertCount(1, $detector->findUnsubstantiated('The bag is biodegradable.', 'certification: eu ecolabel'));
    }

    public function testCarbonNeutralityIsNeverSubstantiated(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertCount(1, $detector->findUnsubstantiated('Carbon neutral shipping.', 'certification: eu ecolabel, carbon neutral: yes'));
    }

    public function testTheSameClaimIsReportedOnce(): void
    {
        self::assertCount(1, (new EnvironmentalClaimDetector())->findUnsubstantiated('Eco-friendly. Really eco-friendly.', ''));
    }
}
