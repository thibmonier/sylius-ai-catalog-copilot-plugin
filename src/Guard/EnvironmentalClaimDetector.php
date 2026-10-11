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
            'eco', 'eco[\s-]*friendly', 'environment(?:al)?(?:ly)?[\s-]*(?:friendly|correct|conscious|safe|sound|responsible|aware)',
            '(?:planet|earth|nature|climate|carbon|ozone)[\s-]*(?:friendly|conscious|smart)', 'eco[\s-]*conscious', 'ecological(?:ly)?',
            'sustainabl[ey]', 'sustainability', 'gentle on the (?:environment|planet)', 'good for (?:the )?(?:planet|environment|earth)',
            'kind to the (?:planet|environment)', 'nature\'s friend', 'energy[\s-]*(?:efficient|saving)', 'bio[\s-]*based',
            'green(?:er|est)', 'go(?:ing)? green', 'green (?:product|choice|alternative|option|solution|living|lifestyle|packaging|credentials|brand|company|energy|shopping)s?',
            // fr
            'éco', 'écologi\p{L}*', 'éco[\s-]*responsables?', 'écolos?', 'respectu(?:eux|euse|euses) (?:de|pour) l\'environnement',
            'respectu(?:eux|euse|euses) de la planète', 'bon(?:ne)?s? pour (?:la planète|l\'environnement)', 'amie?s? de la nature',
            'durables? pour la planète', 'économes? en énergie', 'bio[\s-]*sourcée?s?',
            '(?:produit|choix|alternative|geste|solution|emballage|achat)s? verte?s?', 'plus verte?s?', 'verte?s? pour la planète',
            // de
            'umweltfreundlich\p{L}*', 'umweltschonend\p{L}*', 'umweltbewusst\p{L}*', 'ressourcenschonend\p{L}*', 'klimafreundlich\p{L}*',
            'ökologisch\p{L}*', 'nachhaltig\p{L}*', 'naturfreundlich\p{L}*', 'energieeffizient\p{L}*', 'energiesparend\p{L}*',
            'biobasiert\p{L}*', 'öko(?!nom|[\s-]*tex)\p{L}+', 'gut für (?:die umwelt|den planeten|das klima)',
            'grün(?:e[snrm]?)? (?:produkt|wahl|alternative|lösung|verpackung|energie)\p{L}*',
            // es
            'eco', 'ecológic\p{L}*', 'sostenib\p{L}*', 'sustentab\p{L}*', 'respetuos[oa]s? con el (?:medio ?ambiente|planeta)',
            'amigables? con el (?:medio ?ambiente|planeta)', 'energéticamente eficientes?', 'eficientes? energéticamente',
            'de origen biológico', 'buen[oa]s? para (?:el planeta|el medio ?ambiente)',
            '(?:producto|opción|alternativa|elección|solución)e?s? verdes?',
            // it
            'ecologic\p{L}*', 'eco[\s-]*sostenibil\p{L}*', 'sostenibil\p{L}*', 'rispettos[oaie] (?:dell\'|per l\')ambiente',
            'amic[oaieh]*\p{L}* (?:dell\'|per l\')ambiente', 'a basso impatto ambientale', 'efficienti? dal punto di vista energetico',
            'a base biologica', 'buon[oaie]? per (?:il pianeta|l\'ambiente)', '(?:prodott|scelt|alternativ|soluzion)[oaie] verd[ei]',
            // nl
            'milieuvriendelijk\p{L}*', 'klimaatvriendelijk\p{L}*', 'duurza(?:am|me)\p{L}*', 'ecologisch\p{L}*',
            'natuurvriendelijk\p{L}*', 'energiezuinig\p{L}*', 'biobased', 'goed voor (?:het milieu|de planeet)',
            'groene? (?:keuze|product|alternatief|oplossing|verpakking)\p{L}*',
            // pl
            'ekologiczn\p{L}*', 'eko', 'przyjazn\p{L}* (?:dla )?środowisk\p{L}*', 'przyjazn\p{L}* dla planety',
            'zrównoważon\p{L}*', 'energooszczędn\p{L}*', 'biopochodn\p{L}*', 'dobr\p{L}* dla (?:planety|środowiska)',
            'zielon\p{L}* (?:wybór|produkt|alternatyw\p{L}*|rozwiązani\p{L}*)',
        ],
        self::TYPE_BIODEGRADABLE => [
            'biodegradabl(?:e|es)', 'biodégradables?', 'biologisch abbaubar\p{L}*', 'biodegradabil[ei]', 'biologisch afbreekba(?:ar|re)',
            'biodegradowaln\p{L}*', 'compostable', 'kompostierbar\p{L}*', 'compostables?', 'composta(?:bles?|bili?)',
            'composteerba(?:ar|re)', 'kompostowaln\p{L}*',
        ],
        self::TYPE_CARBON_NEUTRAL => [
            // en
            '(?:carbon|climate|co2|co₂)[\s-]*(?:neutral\p{L}*|positive|negative|free)', 'net[\s-]*zero',
            'zero[\s-]*(?:carbon|emissions?)', 'emissions?[\s-]*free', '(?:carbon|co2|co₂|climate)[\s-]*(?:offset(?:ting)?|compensated)',
            'offset (?:by|through) (?:carbon|co2|co₂|climate)', 'compensated for (?:\p{L}+ ){0,2}(?:carbon|co2|co₂|climate|greenhouse|emissions?)',
            // fr
            'neutres? en (?:carbone|co2|co₂)', 'neutralité (?:carbone|climatique)', 'climatiquement neutres?', 'carboneutr\p{L}*',
            'compensée?s? (?:en )?(?:carbone|co2|co₂)', 'zéro (?:carbone|émissions?)', 'bilan carbone neutre',
            // de
            'klimaneutral\p{L}*', 'klimapositiv\p{L}*', '(?:co2|co₂)[\s-]*(?:neutral|frei)\p{L}*', '(?:co2|co₂|klima)[\s-]*kompensiert\p{L}*',
            'emissionsfrei\p{L}*',
            // es
            'neutr[oa]s? en (?:carbono|co2|co₂)', 'climáticamente neutr[oa]s?', 'neutralidad (?:climática|de carbono|en carbono)',
            'compensad[oa]s? (?:en )?(?:carbono|co2|co₂)', 'cero emisiones',
            // it
            'neutral[ei] (?:in|per (?:il|le)) (?:carbonio|co2|co₂|emissioni)', 'climaticamente neutral[ei]', 'neutralità (?:climatica|carbonica)',
            'a impatto (?:zero|climatico zero)', 'a emissioni zero', 'emissioni zero', 'zero emissioni', 'compensat[oaie] (?:in )?(?:co2|co₂|carbonio)',
            // nl
            'klimaatneutra(?:al|le)', '(?:co2|co₂)[\s-]*neutra(?:al|le)', '(?:co2|co₂|klimaat)[\s-]*gecompenseerd\p{L}*', 'emissievrij\p{L}*',
            // pl
            'neutraln\p{L}* (?:klimatycznie|węglowo|pod względem emisji)', 'neutralność (?:klimatyczn\p{L}*|węglow\p{L}*)',
            'zerow\p{L}* (?:emisj\p{L}*|ślad\p{L}* węglow\p{L}*)', 'skompensowan\p{L}* (?:emisj\p{L}*|co2|co₂|ślad\p{L}*)',
        ],
    ];

    /**
     * Roots of the colour words used in the "green ..." claims above: when the product name,
     * attributes or option values give that colour, those phrases describe the colour.
     */
    public const COLOUR_ROOTS = [
        'green' => 'green(?:er|est)?',
        'grün' => 'grün(?:e[snrm]?)?',
        'vert' => 'vert(?:e|s|es)?',
        'verde' => 'verd[ei]s?',
        'groen' => 'groene?',
        'zielony' => 'zielon(?:y|a|e|ego|ej|ym|ych|ymi)',
    ];

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

    /** A word anywhere in a value that denies, qualifies or postpones it. */
    private const NEGATION = '/(?<![\p{L}\p{N}])(?:no(?!\.\s*\S*\d)|none|non|not|n\/a|false|nein|nicht|kein\p{L}*|ohne|aucun\p{L}*|pas|sans|sin|ningun\p{L}*|ningún|nessun\p{L}*|senza|geen|niet|zonder|brak|nie|bez|does not|doesn\'t|pending|candidate|candidat\p{L}*|kandidat\p{L}*|kandydat\p{L}*|applied|application|applying|requested|in progress|en cours|en attente|demandée?|beantragt|in attesa|richiest\p{L}*|en trámite|solicitad\p{L}*|aangevraagd|w trakcie|expired|expir\p{L}*|abgelaufen|scadut\p{L}*|caducad\p{L}*|verlopen|wygasł\p{L}*|withdrawn|retiré\p{L}*|revoked|suspend\p{L}*|unknown|inconnu\p{L}*|unbekannt|desconocid\p{L}*|sconosciut\p{L}*|onbekend|nieznan\p{L}*|partial\p{L}*|partiel\p{L}*|teilweise|parcial\p{L}*|parzial\p{L}*|gedeeltelijk|częściow\p{L}*|planned|prévu\p{L}*|geplant|previst\p{L}*|soon|bientôt|bald|maybe|peut-être|vielleicht)(?![\p{L}\p{N}])/iu';

    /** Values that state a property: an affirmative word, alone or followed by details. */
    private const AFFIRMATIVE_VALUE = '/^(?:yes|true|1|oui|ja|sí|si|sì|tak|certified|certifié\p{L}*|zertifiziert|certificad[oa]|certificat[oa]|gecertificeerd|certyfikowan\p{L}*)(?:$|[\s,;:(\-])/iu';

    /** Standards stating industrial compostability. */
    private const COMPOSTABILITY_STANDARDS = '/(?<![\p{L}\p{N}])(?:en[\s-]*13432|en[\s-]*14995|iso[\s-]*17088|astm[\s-]*d[\s-]*6400|ok compost)(?![\p{L}\p{N}])/iu';

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

    /**
     * Typographic apostrophes and Unicode hyphens or dashes, as models write them, are matched
     * like their ASCII forms; non-breaking spaces like spaces; soft hyphens are removed.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(['’', '‘', 'ʼ', '`'], '\'', $text);
        $text = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $text);
        $text = (string) preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $text);

        return str_replace("\u{00AD}", '', $text);
    }

    /**
     * Matches of every pattern; a match inside a longer one ("eco" in "eco-friendly") is dropped.
     *
     * @return list<array{claim: string, type: string}>
     */
    private function find(string $text, ProductFacts $facts): array
    {
        $candidates = [];
        foreach (self::CLAIMS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all(self::wrap($pattern), $text, $matches, \PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($matches[0] as [$match, $offset]) {
                        if (self::TYPE_GENERIC === $type && $this->isProductColour($match, $facts)) {
                            continue;
                        }
                        $candidates[] = ['claim' => $match, 'type' => $type, 'start' => $offset, 'end' => $offset + strlen($match)];
                    }
                }
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['end'] - $b['start'], $a['start']] <=> [$a['end'] - $a['start'], $b['start']]);
        $kept = [];
        foreach ($candidates as $candidate) {
            foreach ($kept as $other) {
                if ($candidate['start'] < $other['end'] && $other['start'] < $candidate['end']) {
                    continue 2;
                }
            }
            $kept[] = $candidate;
        }
        usort($kept, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return array_map(static fn (array $claim): array => ['claim' => $claim['claim'], 'type' => $claim['type']], $kept);
    }

    /** "green product" describes the product when its name, attributes or options give that colour. */
    private function isProductColour(string $claim, ProductFacts $facts): bool
    {
        $productText = null;
        foreach (self::COLOUR_ROOTS as $root) {
            if (1 !== preg_match(self::wrap($root), $claim)) {
                continue;
            }

            if (null === $productText) {
                $values = [$facts->name, ...array_column($facts->attributes, 'value')];
                foreach ($facts->options as $option) {
                    $values = [...$values, ...$option['values']];
                }
                $productText = self::normalize(implode("\n", $values));
            }

            if (1 === preg_match(self::wrap($root), $productText)) {
                return true;
            }
        }

        return false;
    }

    /** The attribute substantiating a claim type, as "Name: value", or null. */
    private function evidence(string $type, ProductFacts $facts): ?string
    {
        foreach ($facts->attributes as $attribute) {
            $code = mb_strtolower($attribute['code'] ?? '');
            $value = trim(mb_strtolower(self::normalize($attribute['value'])));
            // Any denying, qualifying or postponing word anywhere disqualifies the value.
            if ('' === $value || 1 === preg_match(self::NEGATION, $value)) {
                continue;
            }
            $affirmative = 1 === preg_match(self::AFFIRMATIVE_VALUE, $value);

            $supports = match ($type) {
                self::TYPE_GENERIC => in_array($code, $this->ecolabelAttributes, true) &&
                    ($this->namesRecognisedLabel($value) ||
                        ($affirmative && $this->namesRecognisedLabel(mb_strtolower($attribute['name']) . ' ' . str_replace('_', ' ', $code)))),
                self::TYPE_BIODEGRADABLE => in_array($code, $this->biodegradableAttributes, true) &&
                    ($affirmative || 1 === preg_match(self::COMPOSTABILITY_STANDARDS, $value)),
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
