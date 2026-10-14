<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

/**
 * Deterministic generator that never calls any AI service: it assembles sentences from the
 * product facts with templates in the target locale (en, fr, de, es, it, nl, pl; English for
 * other languages). It lets the plugin be tried (and tested) without credentials, and the admin
 * labels its suggestions as coming from the "demo generator".
 *
 * "Translate from" requests are rebuilt from the facts of the target locale, not translated.
 * One product out of three gets a generic environmental claim (flagged to the reviewer) and a
 * carbon neutrality claim (always removed), to show the content guard.
 */
final class DemoTextGenerator implements TextGeneratorInterface
{
    public const TYPE = 'demo';

    public const MODEL = 'demo';

    /** @var array<string, array{range: string, discover: string, available: string, claims: string, order: string}> */
    public const TEMPLATES = [
        'en' => [
            'range' => '%name% is part of our %taxon% range.',
            'discover' => 'Discover %name%.',
            'available' => 'Available %option%: %values%.',
            'claims' => 'An eco-friendly choice for every day. Shipped carbon neutral.',
            'order' => 'Order online today.',
        ],
        'fr' => [
            'range' => '%name% fait partie de notre gamme %taxon%.',
            'discover' => 'Découvrez %name%.',
            'available' => '%option% disponibles : %values%.',
            'claims' => 'Un choix écologique pour tous les jours. Livraison neutre en carbone.',
            'order' => 'Commandez en ligne dès aujourd\'hui.',
        ],
        'de' => [
            'range' => '%name% gehört zu unserer Kollektion %taxon%.',
            'discover' => 'Entdecken Sie %name%.',
            'available' => 'Verfügbar in %option%: %values%.',
            'claims' => 'Eine umweltfreundliche Wahl für jeden Tag. Klimaneutral versendet.',
            'order' => 'Jetzt online bestellen.',
        ],
        'es' => [
            'range' => '%name% forma parte de nuestra gama %taxon%.',
            'discover' => 'Descubra %name%.',
            'available' => '%option% disponibles: %values%.',
            'claims' => 'Una opción ecológica para cada día. Envío neutro en carbono.',
            'order' => 'Pídalo hoy en línea.',
        ],
        'it' => [
            'range' => '%name% fa parte della nostra gamma %taxon%.',
            'discover' => 'Scopri %name%.',
            'available' => '%option% disponibili: %values%.',
            'claims' => 'Una scelta ecologica per tutti i giorni. Spedizione a impatto zero.',
            'order' => 'Ordina online oggi.',
        ],
        'nl' => [
            'range' => '%name% maakt deel uit van onze collectie %taxon%.',
            'discover' => 'Ontdek %name%.',
            'available' => 'Beschikbare %option%: %values%.',
            'claims' => 'Een milieuvriendelijke keuze voor elke dag. Klimaatneutraal verzonden.',
            'order' => 'Bestel vandaag online.',
        ],
        'pl' => [
            'range' => '%name% należy do naszej kolekcji %taxon%.',
            'discover' => 'Poznaj %name%.',
            'available' => 'Dostępne %option%: %values%.',
            'claims' => 'Ekologiczny wybór na co dzień. Wysyłka neutralna klimatycznie.',
            'order' => 'Zamów online już dziś.',
        ],
    ];

    public function generate(GenerationRequest $request): GeneratedText
    {
        $text = match ($request->field) {
            ProductField::DESCRIPTION => $this->description($request),
            ProductField::SHORT_DESCRIPTION => $this->shortDescription($request),
            ProductField::META_DESCRIPTION => $this->metaDescription($request),
            ProductField::META_KEYWORDS => $this->metaKeywords($request),
            default => throw new GenerationFailedException(sprintf('The demo generator cannot write the "%s" field.', $request->field)),
        };

        return new GeneratedText($text, self::MODEL);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /** @return array{range: string, discover: string, available: string, claims: string, order: string} */
    public static function templates(string $localeCode): array
    {
        return self::TEMPLATES[strtolower(substr($localeCode, 0, 2))] ?? self::TEMPLATES['en'];
    }

    private function description(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $templates = self::templates($facts->localeCode);
        $paragraphs = [
            [] !== $facts->taxons
                ? strtr($templates['range'], ['%name%' => $facts->name, '%taxon%' => $facts->taxons[0]])
                : strtr($templates['discover'], ['%name%' => $facts->name]),
        ];

        $lines = [];
        foreach ($facts->attributes as $attribute) {
            $lines[] = sprintf('%s: %s.', $attribute['name'], $attribute['value']);
        }
        foreach ($facts->options as $option) {
            $lines[] = $this->ucfirst(strtr($templates['available'], [
                '%option%' => mb_strtolower($option['name']),
                '%values%' => implode(', ', $option['values']),
            ]));
        }
        if ([] !== $lines) {
            $paragraphs[] = implode("\n", $lines);
        }

        if (0 === crc32($facts->code) % 3) {
            $paragraphs[] = $templates['claims'];
        }

        return implode("\n\n", $paragraphs);
    }

    private function shortDescription(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $details = array_map(
            static fn (array $attribute): string => sprintf('%s: %s', $attribute['name'], $attribute['value']),
            array_slice($facts->attributes, 0, 2),
        );

        return [] === $details ? sprintf('%s.', $facts->name) : sprintf('%s (%s).', $facts->name, implode(', ', $details));
    }

    private function metaDescription(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $text = [] !== $facts->taxons ? sprintf('%s - %s.', $facts->name, $facts->taxons[0]) : sprintf('%s.', $facts->name);
        foreach (array_slice($facts->attributes, 0, 3) as $attribute) {
            $text .= sprintf(' %s: %s.', $attribute['name'], $attribute['value']);
        }

        return $text . ' ' . self::templates($facts->localeCode)['order'];
    }

    private function metaKeywords(GenerationRequest $request): string
    {
        $facts = $request->facts;
        $keywords = [$facts->name, ...$facts->taxons];
        foreach ($facts->attributes as $attribute) {
            $keywords[] = $attribute['value'];
        }

        $keywords = array_values(array_unique(array_map(static fn (string $keyword): string => mb_strtolower(trim($keyword)), $keywords)));

        return implode(', ', array_slice($keywords, 0, 10));
    }

    private function ucfirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
