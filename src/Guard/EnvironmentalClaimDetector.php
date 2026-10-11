<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;

/**
 * Finds generic environmental claims (Directive (EU) 2024/825, applicable since 2026-09-27,
 * Annex I points 4a and 4c of Directive 2005/29/EC) in the languages of the plugin translations.
 *
 * Claims are matched with inflection-tolerant patterns (stems + any letters, whole words). A
 * claim is "substantiated" only by an explicit product attribute with a positive value:
 *  - generic claims: an ecolabel attribute (configured codes) naming a recognised label
 *    (EU Ecolabel, EN ISO 14024 type I labels);
 *  - "biodegradable": a biodegradability attribute (configured codes) with a positive value.
 * Carbon or climate neutrality and offsetting claims are never substantiated.
 *
 * This is a keyword list, not a semantic analysis: it reduces the risk, it is no legal advice.
 */
final class EnvironmentalClaimDetector
{
    public const TYPE_GENERIC = 'generic';

    public const TYPE_BIODEGRADABLE = 'biodegradable';

    public const TYPE_CARBON_NEUTRAL = 'carbon_neutral';

    /** Regular expression bodies, matched case-insensitively on whole words. */
    public const CLAIMS = [
        self::TYPE_GENERIC => [
            // en
            'eco[\s-]*friendly', 'environment(?:ally)?[\s-]*(?:friendly|correct|conscious|safe|sound)',
            '(?:planet|earth|nature|climate|carbon|ozone)[\s-]*friendly', 'eco[\s-]*conscious', 'ecological(?:ly)?',
            'sustainabl[ey]', 'sustainability', 'gentle on the (?:environment|planet)', 'good for the (?:planet|environment)',
            'kind to the (?:planet|environment)', 'nature\'s friend', 'energy[\s-]*(?:efficient|saving)', 'bio[\s-]*based',
            // fr
            'écologiques?', 'éco[\s-]*responsables?', 'écolos?', 'respectu(?:eux|euse|euses) (?:de|pour) l\'environnement',
            'respectu(?:eux|euse|euses) de la planète', 'bons? pour la planète', 'bonnes? pour la planète', 'amie?s? de la nature',
            'durables? pour la planète', 'économes? en énergie', 'bio[\s-]*sourcée?s?',
            // de
            'umweltfreundlich\p{L}*', 'umweltschonend\p{L}*', 'klimafreundlich\p{L}*', 'ökologisch\p{L}*', 'nachhaltig\p{L}*',
            'naturfreundlich\p{L}*', 'energieeffizient\p{L}*', 'energiesparend\p{L}*', 'biobasiert\p{L}*', 'öko',
            // es
            'ecológic[oa]s?', 'sostenibles?', 'sustentables?', 'respetuos[oa]s? con el (?:medio ambiente|planeta)',
            'amigables? con el (?:medio ambiente|planeta)', 'energéticamente eficientes?', 'eficientes? energéticamente',
            'de origen biológico',
            // it
            'ecologic[oaie]', 'eco[\s-]*sostenibil[ei]', 'sostenibil[ei]', 'rispettos[oaie] (?:dell\'|per l\')ambiente',
            'amic[oaie]? dell\'ambiente', 'a basso impatto ambientale', 'efficienti? dal punto di vista energetico',
            'a base biologica',
            // nl
            'milieuvriendelijk\p{L}*', 'klimaatvriendelijk\p{L}*', 'duurza(?:am|me)\p{L}*', 'ecologisch\p{L}*',
            'natuurvriendelijk\p{L}*', 'energiezuinig\p{L}*', 'biobased',
            // pl
            'ekologiczn\p{L}*', 'eko', 'przyjazn\p{L}* (?:dla )?środowisk\p{L}*', 'przyjazn\p{L}* dla planety',
            'zrównoważon\p{L}*', 'energooszczędn\p{L}*', 'biopochodn\p{L}*',
        ],
        self::TYPE_BIODEGRADABLE => [
            'biodegradabl(?:e|es)', 'biodégradables?', 'biologisch abbaubar\p{L}*', 'biodegradabil[ei]', 'biologisch afbreekba(?:ar|re)',
            'biodegradowaln\p{L}*', 'compostable', 'kompostierbar\p{L}*', 'compostables?', 'composta(?:bles?|bili?)',
            'composteerba(?:ar|re)', 'kompostowaln\p{L}*',
        ],
        self::TYPE_CARBON_NEUTRAL => [
            '(?:carbon|climate|co2|co₂)[\s-]*(?:neutral|positive|negative|free)', 'net[\s-]*zero', 'zero[\s-]*(?:carbon|emissions?)',
            '(?:carbon|co2|co₂|climate)[\s-]*(?:offset(?:ting)?|compensated)', 'offset (?:by|through) (?:carbon|co2|climate)',
            'neutres? en (?:carbone|co2|co₂)', 'climatiquement neutres?', 'compensée?s? (?:en )?(?:carbone|co2|co₂)',
            'zéro (?:carbone|émissions?)', 'bilan carbone neutre',
            'klimaneutral\p{L}*', 'klimapositiv\p{L}*', '(?:co2|co₂)[\s-]*neutral\p{L}*', '(?:co2|co₂|klima)[\s-]*kompensiert\p{L}*',
            'neutr[oa]s? en (?:carbono|co2|co₂)', 'climáticamente neutr[oa]s?', 'compensad[oa]s? (?:en )?(?:carbono|co2|co₂)',
            'carbon[\s-]*neutral[ei]?', 'neutral[ei] (?:in|per (?:il|le)) (?:carbonio|co2|co₂|emissioni)', 'climaticamente neutral[ei]',
            'a impatto (?:zero|climatico zero)', 'compensat[oaie] (?:in )?(?:co2|co₂|carbonio)',
            'klimaatneutra(?:al|le)', '(?:co2|co₂)[\s-]*neutra(?:al|le)', '(?:co2|co₂|klimaat)[\s-]*gecompenseerd\p{L}*',
            'neutraln\p{L}* (?:klimatycznie|węglowo|pod względem emisji)', 'zerow\p{L}* (?:emisj\p{L}*|ślad\p{L}* węglow\p{L}*)',
            'skompensowan\p{L}* (?:emisj\p{L}*|co2|co₂|ślad\p{L}*)',
        ],
    ];

    /**
     * Colour words that are also generic claims ("a green choice"): a claim unless the product
     * attributes give that colour.
     */
    public const COLOUR_CLAIMS = ['green', 'grün\p{L}*', 'vert(?:e|s|es)?', 'verd[ei]s?', 'groen\p{L}*', 'zielon\p{L}*'];

    /** Recognised ecolabels (EU Ecolabel and EN ISO 14024 type I labels). */
    public const RECOGNISED_LABELS = [
        'eu ecolabel', 'eu-ecolabel', 'ecolabel ue', 'écolabel européen', 'écolabel ue', 'eu-umweltzeichen',
        'etiqueta ecológica de la ue', 'marchio ecolabel ue', 'eu-milieukeur', 'ekoznak ue', 'blauer engel', 'blue angel',
        'nordic swan', 'svanen', 'cygne nordique', 'nordisches umweltzeichen', 'österreichisches umweltzeichen',
        'nf environnement', 'aenor medio ambiente', 'milieukeur', 'iso 14024',
    ];

    /** Default attribute codes holding an ecolabel. */
    public const ECOLABEL_ATTRIBUTES = ['ecolabel', 'eco_label', 'eu_ecolabel', 'environmental_label', 'certification', 'certifications'];

    /** Default attribute codes stating biodegradability or compostability. */
    public const BIODEGRADABLE_ATTRIBUTES = ['biodegradable', 'biodegradability', 'compostable', 'compostability'];

    /** Values (or value prefixes) that deny instead of stating. */
    private const NEGATIVE_VALUE = '/^(?:no|none|non|not|n\/a|na|false|0|nein|nicht|kein\p{L}*|ohne|aucun\p{L}*|pas|sans|ningun\p{L}*|ningún|nessun\p{L}*|senza|geen|niet|zonder|brak|nie|bez|pending|in progress|applied|requested|expired|en cours|en attente|beantragt|in attesa|en trámite|aangevraagd|w trakcie)(?![\p{L}\p{N}])/iu';

    private const AFFIRMATIVE_VALUE = '/^(?:yes|true|1|oui|ja|sí|si|tak|certified|certifié\p{L}*|zertifiziert|certificad[oa]|certificat[oa]|gecertificeerd|certyfikowan\p{L}*)$/iu';

    /** @var list<string> */
    private readonly array $recognisedLabels;

    /** @var list<string> */
    private readonly array $ecolabelAttributes;

    /** @var list<string> */
    private readonly array $biodegradableAttributes;

    /**
     * @param list<string> $extraRecognisedLabels
     * @param list<string>|null $ecolabelAttributes attribute codes, null for the defaults
     * @param list<string>|null $biodegradableAttributes attribute codes, null for the defaults
     */
    public function __construct(array $extraRecognisedLabels = [], ?array $ecolabelAttributes = null, ?array $biodegradableAttributes = null)
    {
        $this->recognisedLabels = self::lower([...self::RECOGNISED_LABELS, ...$extraRecognisedLabels]);
        $this->ecolabelAttributes = self::lower($ecolabelAttributes ?? self::ECOLABEL_ATTRIBUTES);
        $this->biodegradableAttributes = self::lower($biodegradableAttributes ?? self::BIODEGRADABLE_ATTRIBUTES);
    }

    /**
     * Claims found in $text: the unsubstantiated ones, and the ones a product attribute supports
     * (with that attribute as evidence).
     *
     * @return array{unsubstantiated: list<array{claim: string, type: string}>, substantiated: list<array{claim: string, type: string, evidence: string}>}
     */
    public function analyse(string $text, ProductFacts $facts): array
    {
        $text = self::normalize($text);
        $result = ['unsubstantiated' => [], 'substantiated' => []];
        $seen = [];

        foreach ($this->find($text, $facts) as $claim) {
            $key = mb_strtolower($claim['claim']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $evidence = $this->evidence($claim['type'], $facts);
            if (null === $evidence) {
                $result['unsubstantiated'][] = $claim;
            } else {
                $result['substantiated'][] = $claim + ['evidence' => $evidence];
            }
        }

        return $result;
    }

    /** True when $text contains the given claim text (as found by analyse()). */
    public function contains(string $text, string $claim): bool
    {
        $words = preg_split('/[\s\-]+/u', mb_strtolower(self::normalize($claim))) ?: [$claim];
        $body = implode('[\s\-]*', array_map(static fn (string $word): string => preg_quote($word, '/'), $words));

        return 1 === preg_match(self::wrap($body), self::normalize($text));
    }

    /** Typographic apostrophes, as models write them, are matched like straight ones. */
    public static function normalize(string $text): string
    {
        return str_replace(['’', '‘', 'ʼ', '`'], '\'', $text);
    }

    /** @return list<array{claim: string, type: string}> */
    private function find(string $text, ProductFacts $facts): array
    {
        $found = [];
        foreach (self::CLAIMS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all(self::wrap($pattern), $text, $matches) > 0) {
                    foreach ($matches[0] as $match) {
                        $found[] = ['claim' => $match, 'type' => $type];
                    }
                }
            }
        }

        $colours = mb_strtolower(implode("\n", array_column($facts->attributes, 'value')));
        foreach (self::COLOUR_CLAIMS as $pattern) {
            if (preg_match_all(self::wrap($pattern), $text, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    if (1 !== preg_match(self::wrap(preg_quote(mb_strtolower($match), '/')), $colours)) {
                        $found[] = ['claim' => $match, 'type' => self::TYPE_GENERIC];
                    }
                }
            }
        }

        return $found;
    }

    /** The attribute substantiating a claim type, as "Name: value", or null. */
    private function evidence(string $type, ProductFacts $facts): ?string
    {
        foreach ($facts->attributes as $attribute) {
            $code = mb_strtolower($attribute['code'] ?? '');
            $value = trim(mb_strtolower($attribute['value']));
            if ('' === $value || 1 === preg_match(self::NEGATIVE_VALUE, $value)) {
                continue;
            }

            $supports = match ($type) {
                self::TYPE_GENERIC => in_array($code, $this->ecolabelAttributes, true) &&
                    ($this->namesRecognisedLabel($value) ||
                        (1 === preg_match(self::AFFIRMATIVE_VALUE, $value) && $this->namesRecognisedLabel(mb_strtolower($attribute['name']) . ' ' . str_replace('_', ' ', $code)))),
                self::TYPE_BIODEGRADABLE => in_array($code, $this->biodegradableAttributes, true),
                default => false,
            };

            if ($supports) {
                return $attribute['name'] . ': ' . $attribute['value'];
            }
        }

        return null;
    }

    private function namesRecognisedLabel(string $text): bool
    {
        foreach ($this->recognisedLabels as $label) {
            if (1 === preg_match(self::wrap(preg_quote($label, '/')), $text)) {
                return true;
            }
        }

        return false;
    }

    /** Case-insensitive, whole words. */
    private static function wrap(string $body): string
    {
        return '/(?<![\p{L}\p{N}])(?:' . $body . ')(?![\p{L}\p{N}])/iu';
    }

    /**
     * @param array<string> $values
     *
     * @return list<string>
     */
    private static function lower(array $values): array
    {
        return array_values(array_unique(array_map(static fn (string $value): string => mb_strtolower(trim($value)), $values)));
    }
}
