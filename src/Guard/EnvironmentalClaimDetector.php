<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

/**
 * Finds generic environmental claims (Directive (EU) 2024/825, applicable since 2026-09-27)
 * in the languages of the plugin translations. A claim is "substantiated" only when a product
 * attribute supports it: a recognised ecolabel for generic claims, the same property for
 * "biodegradable". Carbon or climate neutrality claims are never substantiated, as claims
 * based on offsetting are banned outright.
 *
 * This is a keyword list, not a semantic analysis: it reduces the risk, it is no legal advice.
 */
final class EnvironmentalClaimDetector
{
    public const TYPE_GENERIC = 'generic';

    public const TYPE_BIODEGRADABLE = 'biodegradable';

    public const TYPE_CARBON_NEUTRAL = 'carbon_neutral';

    public const CLAIMS = [
        self::TYPE_GENERIC => [
            // en
            'eco-friendly', 'ecofriendly', 'environmentally friendly', 'environment friendly', 'environmentally correct',
            'planet-friendly', 'earth-friendly', 'nature-friendly', 'climate-friendly', 'eco-conscious', 'ecological',
            'ecologically', 'sustainable', 'sustainably', 'gentle on the environment', 'good for the planet',
            'kind to the planet', 'green choice', 'green product', 'green alternative', 'green option', 'go green',
            // fr
            'écologique', 'écologiques', 'éco-responsable', 'éco-responsables', 'écoresponsable', 'écoresponsables',
            'respectueux de l\'environnement', 'respectueuse de l\'environnement', 'respectueux de la planète',
            'respectueuse de la planète', 'bon pour la planète', 'ami de la nature', 'écolo',
            // de
            'umweltfreundlich', 'umweltfreundliche', 'umweltfreundlicher', 'umweltfreundliches', 'klimafreundlich',
            'klimafreundliche', 'ökologisch', 'ökologische', 'ökologischer', 'nachhaltig', 'nachhaltige', 'nachhaltiger',
            'nachhaltiges', 'naturfreundlich',
            // es
            'ecológico', 'ecológica', 'ecológicos', 'ecológicas', 'sostenible', 'sostenibles',
            'respetuoso con el medio ambiente', 'respetuosa con el medio ambiente', 'amigable con el medio ambiente',
            // it
            'ecologico', 'ecologica', 'ecologici', 'ecologiche', 'ecosostenibile', 'eco-sostenibile', 'sostenibile',
            'sostenibili', 'rispettoso dell\'ambiente', 'rispettosa dell\'ambiente',
            // nl
            'milieuvriendelijk', 'milieuvriendelijke', 'duurzaam', 'duurzame', 'ecologisch', 'ecologische',
            'klimaatvriendelijk', 'klimaatvriendelijke',
            // pl
            'ekologiczny', 'ekologiczna', 'ekologiczne', 'przyjazny dla środowiska', 'przyjazna dla środowiska',
            'przyjazne dla środowiska', 'zrównoważony', 'zrównoważona', 'zrównoważone',
        ],
        self::TYPE_BIODEGRADABLE => [
            'biodegradable', 'biodegradables', 'biodégradable', 'biodégradables', 'biologisch abbaubar',
            'biologisch abbaubare', 'biodegradabile', 'biodegradabili', 'biologisch afbreekbaar',
            'biologisch afbreekbare', 'biodegradowalny', 'biodegradowalna', 'biodegradowalne',
        ],
        self::TYPE_CARBON_NEUTRAL => [
            'carbon neutral', 'climate neutral', 'co2 neutral', 'co₂ neutral', 'net zero', 'climate positive',
            'carbon positive', 'carbon negative', 'neutre en carbone', 'neutre en co2', 'climatiquement neutre',
            'compensé carbone', 'klimaneutral', 'klimaneutrale', 'co2-neutral', 'co2-neutrale', 'neutro en carbono',
            'climáticamente neutro', 'a impatto zero', 'neutrale in co2', 'klimaatneutraal', 'klimaatneutrale',
            'co2-neutraal', 'neutralny węglowo', 'neutralny klimatycznie', 'neutralna klimatycznie',
        ],
    ];

    /** Recognised ecolabels (EU Ecolabel and EN ISO 14024 type I labels) that substantiate a generic claim. */
    public const RECOGNISED_LABELS = [
        'eu ecolabel', 'ecolabel', 'écolabel', 'ecolabel ue', 'eu-ecolabel', 'umweltzeichen', 'blauer engel',
        'blue angel', 'nordic swan', 'svanen', 'cygne nordique', 'milieukeur', 'iso 14024',
    ];

    private const BIODEGRADABLE_EVIDENCE = [
        'biodegrad', 'biodégrad', 'biologisch abbaubar', 'biologisch afbreekbaar', 'en 13432',
    ];

    /** @var list<string> */
    private readonly array $recognisedLabels;

    /** @param list<string> $extraRecognisedLabels */
    public function __construct(array $extraRecognisedLabels = [])
    {
        $this->recognisedLabels = array_values(array_unique(array_map(
            static fn (string $label): string => mb_strtolower(trim($label)),
            [...self::RECOGNISED_LABELS, ...$extraRecognisedLabels],
        )));
    }

    /**
     * Unsubstantiated claims found in $text.
     *
     * @return list<array{claim: string, type: string}>
     */
    public function findUnsubstantiated(string $text, string $attributesText): array
    {
        $found = [];
        foreach (self::CLAIMS as $type => $terms) {
            if ($this->isSubstantiated($type, $attributesText)) {
                continue;
            }

            foreach ($terms as $term) {
                if (1 === preg_match(self::pattern($term), $text, $match)) {
                    $found[mb_strtolower($match[0])] ??= ['claim' => $match[0], 'type' => $type];
                }
            }
        }

        return array_values($found);
    }

    /** True when $text contains one of the given claims. */
    public function contains(string $text, string $claim): bool
    {
        return 1 === preg_match(self::pattern($claim), $text);
    }

    private function isSubstantiated(string $type, string $attributesText): bool
    {
        $evidence = match ($type) {
            self::TYPE_GENERIC => $this->recognisedLabels,
            self::TYPE_BIODEGRADABLE => self::BIODEGRADABLE_EVIDENCE,
            default => [],
        };

        foreach ($evidence as $term) {
            if (str_contains($attributesText, $term)) {
                return true;
            }
        }

        return false;
    }

    /** Case-insensitive, whole words, any space or hyphen between the words of the term. */
    private static function pattern(string $term): string
    {
        $words = preg_split('/[\s\-]+/u', mb_strtolower($term)) ?: [$term];
        $body = implode('[\s\-]*', array_map(static fn (string $word): string => preg_quote($word, '/'), $words));

        return '/(?<![\p{L}\p{N}])' . $body . '(?![\p{L}\p{N}])/iu';
    }
}
