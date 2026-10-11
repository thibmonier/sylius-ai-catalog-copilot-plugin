<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Guard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;

final class EnvironmentalClaimDetectorTest extends TestCase
{
    private const GENERIC = EnvironmentalClaimDetector::TYPE_GENERIC;

    private const BIODEGRADABLE = EnvironmentalClaimDetector::TYPE_BIODEGRADABLE;

    private const NEUTRAL = EnvironmentalClaimDetector::TYPE_CARBON_NEUTRAL;

    /** @return iterable<string, array{string, string, string}> text, claim found, type */
    public static function claims(): iterable
    {
        $claims = [
            'en' => [
                ['A truly Eco-Friendly shirt.', 'Eco-Friendly', self::GENERIC],
                ['An eco friendly shirt.', 'eco friendly', self::GENERIC],
                ['Made to be sustainable.', 'sustainable', self::GENERIC],
                ['A green mug.', 'green', self::GENERIC],
                ['An energy efficient kettle.', 'energy efficient', self::GENERIC],
                ['Carbon friendly delivery.', 'Carbon friendly', self::GENERIC],
                ['A climate-friendly choice.', 'climate-friendly', self::GENERIC],
                ['Made of bio-based plastic.', 'bio-based', self::GENERIC],
                ['An environmentally conscious brand.', 'environmentally conscious', self::GENERIC],
                ['Gentle on the environment.', 'Gentle on the environment', self::GENERIC],
                ['Nature’s friend.', "Nature's friend", self::GENERIC],
                ['The bag is biodegradable.', 'biodegradable', self::BIODEGRADABLE],
                ['A carbon-neutral delivery.', 'carbon-neutral', self::NEUTRAL],
                ['Climate neutral shipping.', 'Climate neutral', self::NEUTRAL],
                ['CO2 neutral since 2020.', 'CO2 neutral', self::NEUTRAL],
                ['Net zero production.', 'Net zero', self::NEUTRAL],
                ['Carbon offset shipping.', 'Carbon offset', self::NEUTRAL],
            ],
            'fr' => [
                ['Un t-shirt écologique.', 'écologique', self::GENERIC],
                ['Des choix éco-responsables.', 'éco-responsables', self::GENERIC],
                ['Des produits écolos.', 'écolos', self::GENERIC],
                ['Des matières respectueuses de l’environnement.', "respectueuses de l'environnement", self::GENERIC],
                ['Un tissu respectueux de l\'environnement.', "respectueux de l'environnement", self::GENERIC],
                ['Une bouilloire économe en énergie.', 'économe en énergie', self::GENERIC],
                ['Un plastique biosourcé.', 'biosourcé', self::GENERIC],
                ['Un sac vert pour la planète.', 'vert', self::GENERIC],
                ['Des sacs biodégradables.', 'biodégradables', self::BIODEGRADABLE],
                ['Livraison neutre en carbone.', 'neutre en carbone', self::NEUTRAL],
                ['Une usine climatiquement neutre.', 'climatiquement neutre', self::NEUTRAL],
                ['Une livraison compensée en carbone.', 'compensée en carbone', self::NEUTRAL],
            ],
            'de' => [
                ['Ein umweltfreundliches Produkt.', 'umweltfreundliches', self::GENERIC],
                ['Ein umweltfreundlichen Becher.', 'umweltfreundlichen', self::GENERIC],
                ['Mit umweltfreundlichem Material.', 'umweltfreundlichem', self::GENERIC],
                ['Ein nachhaltigen Produkt.', 'nachhaltigen', self::GENERIC],
                ['Ein ökologisches Produkt.', 'ökologisches', self::GENERIC],
                ['Ein energieeffizienter Wasserkocher.', 'energieeffizienter', self::GENERIC],
                ['Biobasierter Kunststoff.', 'Biobasierter', self::GENERIC],
                ['Die Tüte ist biologisch abbaubar.', 'biologisch abbaubar', self::BIODEGRADABLE],
                ['Eine biologisch abbaubaren Tüte.', 'biologisch abbaubaren', self::BIODEGRADABLE],
                ['Klimaneutral hergestellt.', 'Klimaneutral', self::NEUTRAL],
                ['Mit klimaneutralen Versand.', 'klimaneutralen', self::NEUTRAL],
                ['Mit CO2-neutralen Versand.', 'CO2-neutralen', self::NEUTRAL],
                ['CO2-kompensierter Versand.', 'CO2-kompensierter', self::NEUTRAL],
            ],
            'es' => [
                ['Una camiseta sostenible.', 'sostenible', self::GENERIC],
                ['Una taza ecológica.', 'ecológica', self::GENERIC],
                ['Productos respetuosos con el medio ambiente.', 'respetuosos con el medio ambiente', self::GENERIC],
                ['Una bolsa biodegradable.', 'biodegradable', self::BIODEGRADABLE],
                ['Envío neutro en carbono.', 'neutro en carbono', self::NEUTRAL],
                ['Una fábrica neutra en carbono.', 'neutra en carbono', self::NEUTRAL],
                ['Una empresa climáticamente neutra.', 'climáticamente neutra', self::NEUTRAL],
            ],
            'it' => [
                ['Una maglietta ecosostenibile.', 'ecosostenibile', self::GENERIC],
                ['Prodotti ecologici.', 'ecologici', self::GENERIC],
                ['Un tessuto rispettoso dell’ambiente.', "rispettoso dell'ambiente", self::GENERIC],
                ['Una borsa biodegradabile.', 'biodegradabile', self::BIODEGRADABLE],
                ['Spedizione a impatto zero.', 'a impatto zero', self::NEUTRAL],
                ['Spedizione compensata in CO2.', 'compensata in CO2', self::NEUTRAL],
            ],
            'nl' => [
                ['Een duurzaam shirt.', 'duurzaam', self::GENERIC],
                ['Duurzame materialen.', 'Duurzame', self::GENERIC],
                ['Een milieuvriendelijke tas.', 'milieuvriendelijke', self::GENERIC],
                ['Een groen product.', 'groen', self::GENERIC],
                ['Een energiezuinige waterkoker.', 'energiezuinige', self::GENERIC],
                ['Een biologisch afbreekbare tas.', 'biologisch afbreekbare', self::BIODEGRADABLE],
                ['Klimaatneutrale verzending.', 'Klimaatneutrale', self::NEUTRAL],
                ['CO2-gecompenseerde verzending.', 'CO2-gecompenseerde', self::NEUTRAL],
            ],
            'pl' => [
                ['Ekologiczna koszulka.', 'Ekologiczna', self::GENERIC],
                ['Z ekologicznego kubka.', 'ekologicznego', self::GENERIC],
                ['Z ekologicznym materiałem.', 'ekologicznym', self::GENERIC],
                ['Kubek przyjaznego dla środowiska.', 'przyjaznego dla środowiska', self::GENERIC],
                ['Ze zrównoważonej produkcji.', 'zrównoważonej', self::GENERIC],
                ['Energooszczędny czajnik.', 'Energooszczędny', self::GENERIC],
                ['Biodegradowalna torba.', 'Biodegradowalna', self::BIODEGRADABLE],
                ['Wysyłka neutralna klimatycznie.', 'neutralna klimatycznie', self::NEUTRAL],
                ['Produkty neutralne klimatycznie.', 'neutralne klimatycznie', self::NEUTRAL],
            ],
        ];

        foreach ($claims as $language => $cases) {
            foreach ($cases as [$text, $claim, $type]) {
                yield sprintf('%s: %s', $language, $claim) => [$text, $claim, $type];
            }
        }
    }

    #[DataProvider('claims')]
    public function testItFindsClaimsInEveryLanguageAndInflection(string $text, string $claim, string $type): void
    {
        $analysis = (new EnvironmentalClaimDetector())->analyse($text, $this->facts());

        self::assertSame([['claim' => $claim, 'type' => $type]], $analysis['unsubstantiated']);
        self::assertSame([], $analysis['substantiated']);
    }

    public function testItMatchesWholeWordsOnly(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertSame([], $detector->analyse('Unsustainableness, greenery, ecologist, Greenwich, everdeen.', $this->facts())['unsubstantiated']);
    }

    public function testAColourGivenByTheAttributesIsNotAClaim(): void
    {
        $facts = $this->facts([['name' => 'Colour', 'value' => 'Green', 'code' => 'color']]);

        self::assertSame([], (new EnvironmentalClaimDetector())->analyse('A green shirt.', $facts)['unsubstantiated']);
    }

    public function testARecognisedEcolabelAttributeSubstantiatesGenericClaims(): void
    {
        $detector = new EnvironmentalClaimDetector();

        $analysis = $detector->analyse('An eco-friendly shirt.', $this->facts([['name' => 'Ecolabel', 'value' => 'EU Ecolabel (FR/016/012)', 'code' => 'ecolabel']]));
        self::assertSame([], $analysis['unsubstantiated']);
        self::assertSame([['claim' => 'eco-friendly', 'type' => self::GENERIC, 'evidence' => 'Ecolabel: EU Ecolabel (FR/016/012)']], $analysis['substantiated']);

        $analysis = $detector->analyse('Ein nachhaltiges Hemd.', $this->facts([['name' => 'EU Ecolabel', 'value' => 'ja', 'code' => 'eu_ecolabel']]));
        self::assertSame([], $analysis['unsubstantiated']);
    }

    /** @return iterable<string, array{array{name: string, value: string, code: string}}> */
    public static function nonEvidence(): iterable
    {
        yield 'negative value' => [['name' => 'Ecolabel', 'value' => 'no', 'code' => 'ecolabel']];
        yield 'none' => [['name' => 'Ecolabel', 'value' => 'None', 'code' => 'ecolabel']];
        yield 'not a label' => [['name' => 'Certification', 'value' => 'Not EU Ecolabel', 'code' => 'certification']];
        yield 'pending' => [['name' => 'Ecolabel', 'value' => 'pending application for EU Ecolabel', 'code' => 'ecolabel']];
        yield 'French negative' => [['name' => 'Écolabel', 'value' => 'aucun', 'code' => 'ecolabel']];
        yield 'unrecognised label' => [['name' => 'Certification', 'value' => 'Green Planet Approved', 'code' => 'certification']];
        yield 'label in another attribute' => [['name' => 'Description', 'value' => 'EU Ecolabel', 'code' => 'notes']];
        yield 'affirmative without label' => [['name' => 'Ecolabel', 'value' => 'yes', 'code' => 'ecolabel']];
    }

    /** @param array{name: string, value: string, code: string} $attribute */
    #[DataProvider('nonEvidence')]
    public function testOnlyAPositiveRecognisedLabelSubstantiates(array $attribute): void
    {
        $analysis = (new EnvironmentalClaimDetector())->analyse('An eco-friendly shirt.', $this->facts([$attribute]));

        self::assertCount(1, $analysis['unsubstantiated']);
        self::assertSame([], $analysis['substantiated']);
    }

    public function testEcolabelAttributesAndLabelsAreConfigurable(): void
    {
        $detector = new EnvironmentalClaimDetector(['My National Label'], ['green_label']);

        self::assertSame([], $detector->analyse('Ein nachhaltiges Hemd.', $this->facts([['name' => 'Label', 'value' => 'My National Label', 'code' => 'green_label']]))['unsubstantiated']);
        self::assertCount(1, $detector->analyse('Ein nachhaltiges Hemd.', $this->facts([['name' => 'Ecolabel', 'value' => 'EU Ecolabel', 'code' => 'ecolabel']]))['unsubstantiated']);
    }

    public function testBiodegradableNeedsAPositiveBiodegradabilityAttribute(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertSame([], $detector->analyse('The bag is biodegradable.', $this->facts([['name' => 'Biodegradable', 'value' => 'yes (EN 13432)', 'code' => 'biodegradable']]))['unsubstantiated']);
        self::assertCount(1, $detector->analyse('The bag is biodegradable.', $this->facts([['name' => 'Biodegradable', 'value' => 'No', 'code' => 'biodegradable']]))['unsubstantiated']);
        self::assertCount(1, $detector->analyse('The bag is biodegradable.', $this->facts([['name' => 'Not biodegradable', 'value' => 'yes', 'code' => 'not_biodegradable']]))['unsubstantiated']);
        self::assertCount(1, $detector->analyse('The bag is biodegradable.', $this->facts([['name' => 'Ecolabel', 'value' => 'EU Ecolabel', 'code' => 'ecolabel']]))['unsubstantiated']);
    }

    public function testCarbonNeutralityIsNeverSubstantiated(): void
    {
        $facts = $this->facts([
            ['name' => 'Ecolabel', 'value' => 'EU Ecolabel', 'code' => 'ecolabel'],
            ['name' => 'Carbon neutral', 'value' => 'yes', 'code' => 'carbon_neutral'],
        ]);

        self::assertCount(1, (new EnvironmentalClaimDetector())->analyse('Carbon neutral shipping.', $facts)['unsubstantiated']);
    }

    public function testTheSameClaimIsReportedOnceAndContainsMatchesTypographicApostrophes(): void
    {
        $detector = new EnvironmentalClaimDetector();

        self::assertCount(1, $detector->analyse('Eco-friendly. Really eco-friendly.', $this->facts())['unsubstantiated']);
        self::assertTrue($detector->contains('Un tissu respectueux de l’environnement.', "respectueux de l'environnement"));
    }

    /** @param list<array{name: string, value: string, code: string}> $attributes */
    private function facts(array $attributes = []): ProductFacts
    {
        return new ProductFacts('en_US', 'SHIRT', 'Shirt', [], $attributes);
    }
}
