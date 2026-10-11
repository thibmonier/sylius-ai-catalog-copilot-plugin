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
                ['A green product for every day.', 'green product', self::GENERIC],
                ['The greener choice.', 'greener', self::GENERIC],
                ['Our greenest mug.', 'greenest', self::GENERIC],
                ['An eco bag.', 'eco', self::GENERIC],
                ['An environmentally responsible brand.', 'environmentally responsible', self::GENERIC],
                ['A climate-conscious kettle.', 'climate-conscious', self::GENERIC],
                ['Good for the planet.', 'Good for the planet', self::GENERIC],
                ['Eco‑friendly packaging.', 'Eco-friendly', self::GENERIC],
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
                ['Committed to carbon neutrality.', 'carbon neutrality', self::NEUTRAL],
                ['Produced carbon-neutrally.', 'carbon-neutrally', self::NEUTRAL],
                ['Emissions-free delivery.', 'Emissions-free', self::NEUTRAL],
                ['Delivery compensated for carbon emissions.', 'compensated for carbon emissions', self::NEUTRAL],
                ['A carbon–neutral delivery.', 'carbon-neutral', self::NEUTRAL],
            ],
            'fr' => [
                ['Un t-shirt écologique.', 'écologique', self::GENERIC],
                ['Des choix éco-responsables.', 'éco-responsables', self::GENERIC],
                ['Des produits écolos.', 'écolos', self::GENERIC],
                ['Des matières respectueuses de l’environnement.', "respectueuses de l'environnement", self::GENERIC],
                ['Un tissu respectueux de l\'environnement.', "respectueux de l'environnement", self::GENERIC],
                ['Une bouilloire économe en énergie.', 'économe en énergie', self::GENERIC],
                ['Un plastique biosourcé.', 'biosourcé', self::GENERIC],
                ['Un sac vert pour la planète.', 'vert pour la planète', self::GENERIC],
                ['Un produit vert.', 'produit vert', self::GENERIC],
                ['Fabriqué écologiquement.', 'écologiquement', self::GENERIC],
                ['Bon pour l’environnement.', "Bon pour l'environnement", self::GENERIC],
                ['Un sac éco.', 'éco', self::GENERIC],
                ['Des sacs biodégradables.', 'biodégradables', self::BIODEGRADABLE],
                ['Livraison neutre en carbone.', 'neutre en carbone', self::NEUTRAL],
                ['Une usine climatiquement neutre.', 'climatiquement neutre', self::NEUTRAL],
                ['Une livraison compensée en carbone.', 'compensée en carbone', self::NEUTRAL],
                ['Objectif neutralité carbone.', 'neutralité carbone', self::NEUTRAL],
                ['Une usine carboneutre.', 'carboneutre', self::NEUTRAL],
            ],
            'de' => [
                ['Ein umweltfreundliches Produkt.', 'umweltfreundliches', self::GENERIC],
                ['Ein umweltfreundlichen Becher.', 'umweltfreundlichen', self::GENERIC],
                ['Mit umweltfreundlichem Material.', 'umweltfreundlichem', self::GENERIC],
                ['Ein nachhaltigen Produkt.', 'nachhaltigen', self::GENERIC],
                ['Ein ökologisches Produkt.', 'ökologisches', self::GENERIC],
                ['Ein energieeffizienter Wasserkocher.', 'energieeffizienter', self::GENERIC],
                ['Biobasierter Kunststoff.', 'Biobasierter', self::GENERIC],
                ['Ein umweltbewusstes Unternehmen.', 'umweltbewusstes', self::GENERIC],
                ['Ressourcenschonend hergestellt.', 'Ressourcenschonend', self::GENERIC],
                ['Unsere Ökotasche.', 'Ökotasche', self::GENERIC],
                ['Gut für die Umwelt.', 'Gut für die Umwelt', self::GENERIC],
                ['Ein grünes Produkt.', 'grünes Produkt', self::GENERIC],
                ['Die Tüte ist biologisch abbaubar.', 'biologisch abbaubar', self::BIODEGRADABLE],
                ['Eine biologisch abbaubaren Tüte.', 'biologisch abbaubaren', self::BIODEGRADABLE],
                ['Klimaneutral hergestellt.', 'Klimaneutral', self::NEUTRAL],
                ['Mit klimaneutralen Versand.', 'klimaneutralen', self::NEUTRAL],
                ['Mit CO2-neutralen Versand.', 'CO2-neutralen', self::NEUTRAL],
                ['CO2-kompensierter Versand.', 'CO2-kompensierter', self::NEUTRAL],
                ['CO2-frei geliefert.', 'CO2-frei', self::NEUTRAL],
            ],
            'es' => [
                ['Una camiseta sostenible.', 'sostenible', self::GENERIC],
                ['Una taza ecológica.', 'ecológica', self::GENERIC],
                ['Productos respetuosos con el medio ambiente.', 'respetuosos con el medio ambiente', self::GENERIC],
                ['Respetuoso con el medioambiente.', 'Respetuoso con el medioambiente', self::GENERIC],
                ['Fabricado ecológicamente.', 'ecológicamente', self::GENERIC],
                ['Nuestra sostenibilidad.', 'sostenibilidad', self::GENERIC],
                ['Una bolsa eco.', 'eco', self::GENERIC],
                ['Bueno para el planeta.', 'Bueno para el planeta', self::GENERIC],
                ['Una bolsa biodegradable.', 'biodegradable', self::BIODEGRADABLE],
                ['Envío neutro en carbono.', 'neutro en carbono', self::NEUTRAL],
                ['Una fábrica neutra en carbono.', 'neutra en carbono', self::NEUTRAL],
                ['Una empresa climáticamente neutra.', 'climáticamente neutra', self::NEUTRAL],
                ['Con neutralidad climática.', 'neutralidad climática', self::NEUTRAL],
            ],
            'it' => [
                ['Una maglietta ecosostenibile.', 'ecosostenibile', self::GENERIC],
                ['Prodotti ecologici.', 'ecologici', self::GENERIC],
                ['Un tessuto rispettoso dell’ambiente.', "rispettoso dell'ambiente", self::GENERIC],
                ['Borse ecologiche.', 'ecologiche', self::GENERIC],
                ['Borse amiche dell’ambiente.', "amiche dell'ambiente", self::GENERIC],
                ['La nostra sostenibilità.', 'sostenibilità', self::GENERIC],
                ['Una borsa eco.', 'eco', self::GENERIC],
                ['Buono per il pianeta.', 'Buono per il pianeta', self::GENERIC],
                ['Una borsa biodegradabile.', 'biodegradabile', self::BIODEGRADABLE],
                ['Spedizione a impatto zero.', 'a impatto zero', self::NEUTRAL],
                ['Spedizione compensata in CO2.', 'compensata in CO2', self::NEUTRAL],
                ['Consegna a emissioni zero.', 'a emissioni zero', self::NEUTRAL],
            ],
            'nl' => [
                ['Een duurzaam shirt.', 'duurzaam', self::GENERIC],
                ['Duurzame materialen.', 'Duurzame', self::GENERIC],
                ['Een milieuvriendelijke tas.', 'milieuvriendelijke', self::GENERIC],
                ['Een groen product.', 'groen product', self::GENERIC],
                ['Goed voor het milieu.', 'Goed voor het milieu', self::GENERIC],
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
                ['Dobry dla planety.', 'Dobry dla planety', self::GENERIC],
                ['Biodegradowalna torba.', 'Biodegradowalna', self::BIODEGRADABLE],
                ['Wysyłka neutralna klimatycznie.', 'neutralna klimatycznie', self::NEUTRAL],
                ['Produkty neutralne klimatycznie.', 'neutralne klimatycznie', self::NEUTRAL],
            ],
        ];

        foreach ($claims as $language => $cases) {
            foreach ($cases as $index => [$text, $claim, $type]) {
                yield sprintf('%s #%d: %s', $language, $index, $claim) => [$text, $claim, $type];
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

    /** @return iterable<string, array{string, list<array{name: string, value: string, code: string}>, string}> text, attributes, product name */
    public static function notClaims(): iterable
    {
        $notClaims = [
            'en' => [
                ['Unsustainableness, greenery, ecologist, Greenwich, economy, ecommerce.', [], 'Mug'],
                ['A green T-shirt with a green collar.', [], 'Tee'],
                ['A green product, perfect for tea.', [], 'Green Tea Mug'],
                ['The green alternative to black.', [['name' => 'Colour', 'value' => 'Green', 'code' => 'color']], 'Mug'],
                ['Certified OEKO-TEX Standard 100.', [], 'Tee'],
            ],
            'fr' => [
                ['Un t-shirt vert et une veste verte.', [], 'Tee'],
                ['Un produit vert pomme.', [['name' => 'Couleur', 'value' => 'Vert', 'code' => 'color']], 'Tee'],
                ['Une économie de temps.', [], 'Tee'],
            ],
            'de' => [
                ['Gründlich gewaschen, vom Gründer entworfen, aus guten Gründen.', [], 'Hemd'],
                ['Ein grünes Hemd.', [], 'Hemd'],
                ['Ein grünes Produkt.', [['name' => 'Farbe', 'value' => 'Grün', 'code' => 'color']], 'Hemd'],
                ['Zertifiziert nach Öko-Tex Standard 100 und Ökotex.', [], 'Hemd'],
                ['Ökonomisch und praktisch.', [], 'Hemd'],
            ],
            'es' => [
                ['Una camiseta verde.', [], 'Camiseta'],
                ['Un producto verde.', [], 'Camiseta Verde'],
            ],
            'it' => [
                ['Una maglietta verde.', [], 'Maglietta'],
            ],
            'nl' => [
                ['Verse groenten en een groene trui.', [], 'Trui'],
                ['Een groen product.', [], 'Groene trui'],
            ],
            'pl' => [
                ['Zielona koszulka.', [], 'Koszulka'],
            ],
        ];

        foreach ($notClaims as $language => $cases) {
            foreach ($cases as $index => [$text, $attributes, $name]) {
                yield sprintf('%s #%d', $language, $index) => [$text, $attributes, $name];
            }
        }
    }

    /** @param list<array{name: string, value: string, code: string}> $attributes */
    #[DataProvider('notClaims')]
    public function testColoursCertificationsAndLookAlikeWordsAreNotClaims(string $text, array $attributes, string $name): void
    {
        $facts = new ProductFacts('en_US', 'CODE', $name, [], $attributes);

        self::assertSame([], (new EnvironmentalClaimDetector())->analyse($text, $facts)['unsubstantiated']);
    }

    public function testAColourGivenByAnOptionValueIsNotAClaim(): void
    {
        $facts = new ProductFacts('fr_FR', 'CODE', 'Tee', [], [], [['name' => 'Couleur', 'values' => ['Bleu', 'Verte']]]);

        self::assertSame([], (new EnvironmentalClaimDetector())->analyse('Un produit vert.', $facts)['unsubstantiated']);
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

        // A registration number is not a negation.
        $analysis = $detector->analyse('An eco-friendly shirt.', $this->facts([['name' => 'Ecolabel', 'value' => 'EU Ecolabel no. FR/016/012', 'code' => 'ecolabel']]));
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
        yield 'negation after the label' => [['name' => 'Ecolabel', 'value' => 'EU Ecolabel: no', 'code' => 'ecolabel']];
        yield 'does not meet' => [['name' => 'Certification', 'value' => 'Does not meet EU Ecolabel', 'code' => 'certification']];
        yield 'expired' => [['name' => 'Ecolabel', 'value' => 'EU Ecolabel (expired)', 'code' => 'ecolabel']];
        yield 'candidate' => [['name' => 'Ecolabel', 'value' => 'Candidate for EU Ecolabel', 'code' => 'ecolabel']];
        yield 'German expired' => [['name' => 'Umweltzeichen', 'value' => 'Blauer Engel (abgelaufen)', 'code' => 'ecolabel']];
        yield 'Polish negation' => [['name' => 'Ekoznak', 'value' => 'nie, EU Ecolabel w trakcie', 'code' => 'ecolabel']];
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
        self::assertSame([], $detector->analyse('The bag is compostable.', $this->facts([['name' => 'Compostable', 'value' => 'EN 13432 certified', 'code' => 'compostable']]))['unsubstantiated']);
        self::assertSame([], $detector->analyse('Die Tüte ist biologisch abbaubar.', $this->facts([['name' => 'Biologisch abbaubar', 'value' => 'Ja', 'code' => 'biodegradable']]))['unsubstantiated']);
        foreach (['No', 'Unknown', '-', '?', 'Partially', 'n/a', 'yes, partially', 'Teilweise'] as $value) {
            self::assertCount(1, $detector->analyse('The bag is biodegradable.', $this->facts([['name' => 'Biodegradable', 'value' => $value, 'code' => 'biodegradable']]))['unsubstantiated'], $value);
        }
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
