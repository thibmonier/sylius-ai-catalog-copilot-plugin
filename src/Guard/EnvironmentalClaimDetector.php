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
            'energy[\s-]*efficiency(?![\s-]*(?:class|rating|label|grade))', '(?:good|better|kinder|kind) (?:for|to) (?:the |our )?(?:planet|environment|earth)',
            'friendly to the (?:environment|planet)', 'green(?:er|est)', 'go(?:ing)? green',
            // fr
            'éco', 'écologi\p{L}*', 'éco[\s-]*responsables?', 'écolos?', 'éco[\s-]*conçue?s?',
            'respectu(?:eux|euse|euses) (?:de|pour|envers) (?:l\'environnement|la planète|la nature)',
            'responsables? envers l\'environnement', 'bon(?:ne)?s? pour (?:la planète|l\'environnement)', 'amie?s? de la nature',
            'durables? pour la planète', 'économes? en énergie', 'bio[\s-]*sourcée?s?',
            'efficacité énergétique(?! (?:de )?classe)', 'plus verte?s?', 'verte?s? pour la planète',
            // de
            'umweltfreundlich\p{L}*', 'umweltschonend\p{L}*', 'umweltbewusst\p{L}*', 'ressourcenschonend\p{L}*', 'klimafreundlich\p{L}*',
            'ökologisch\p{L}*', 'nachhaltig\p{L}*', 'naturfreundlich\p{L}*', 'energieeffizient\p{L}*', 'energiesparend\p{L}*',
            'energieeffizienz(?!klasse)\p{L}*', 'umweltverträglich\p{L}*', 'biobasiert\p{L}*',
            'öko(?!nom|system|logi|[\s-]*tex)\p{L}+', 'öko-(?!tex)\p{L}+',
            'gut für(?:s| unsere?n?| die| den| das)? (?:umwelt|klima|planeten)',
            // es
            'ecológic\p{L}*', 'sostenib\p{L}*', 'sustentab\p{L}*', 'respetuos[oa]s? con el (?:medio ?ambiente|ambiente|planeta)',
            'amigables? con el (?:medio ?ambiente|ambiente|planeta)', 'energéticamente eficientes?', 'eficientes? energéticamente',
            'eficiencia energética(?! (?:de )?clase)', 'de origen biológico', 'buen[oa]s? para (?:el planeta|el medio ?ambiente)',
            // it
            'ecologic\p{L}*', 'eco[\s-]*sostenibil\p{L}*', 'sostenibil\p{L}*', 'rispettos[oaie] (?:dell\'ambiente|per l\'ambiente|della natura)',
            'amic[oaieh]*\p{L}* (?:dell\'ambiente|per l\'ambiente|del pianeta)', 'a basso impatto ambientale', 'efficienti? dal punto di vista energetico',
            'efficienza energetica(?! (?:di )?classe)', 'a base biologica', 'buon[oaie]? per (?:il pianeta|l\'ambiente)',
            // nl
            'milieuvriendelijk\p{L}*', 'klimaatvriendelijk\p{L}*', 'duurza(?:am|me)\p{L}*', 'ecologisch\p{L}*',
            'natuurvriendelijk\p{L}*', 'energiezuinig\p{L}*', 'milieubewust\p{L}*', 'biobased',
            'goed voor (?:het |onze )?(?:milieu|klimaat|planeet)',
            // pl
            'ekologiczn\p{L}*', 'eko', 'przyjazn\p{L}* (?:dla )?środowisk\p{L}*', 'przyjazn\p{L}* dla planety',
            'zrównoważon\p{L}*', 'energooszczędn\p{L}*', 'biopochodn\p{L}*', 'dobr\p{L}* dla (?:planety|środowiska)',
        ],
        self::TYPE_BIODEGRADABLE => [
            'biodegradabl(?:e|es)', 'biodégradables?', 'biologisch abbaubar\p{L}*', 'biodegradabil[ei]', 'biologisch afbreekba(?:ar|re)',
            'biodegradowaln\p{L}*', 'compostable', 'kompostierbar\p{L}*', 'compostables?', 'composta(?:bles?|bili?)',
            'composteerba(?:ar|re)', 'kompostowaln\p{L}*',
        ],
        self::TYPE_CARBON_NEUTRAL => [
            // en
            '(?:carbon|climate|co2|co₂)[\s-]*(?:neutral\p{L}*|positive|negative|free|zero)', 'net[\s-]*z[eé]ro', 'z[eé]ro[\s-]*net',
            '(?:ghg|greenhouse[\s-]*gas)[\s-]*neutral\p{L}*', 'offset(?:s|ting)? (?:\p{L}+ ){0,3}(?:emissions?|carbon|co2|co₂|footprint|greenhouse)',
            'carbon footprint (?:\p{L}+ ){0,3}offset', 'compensat\p{L}* (?:for )?(?:\p{L}+ ){0,2}(?:emissions?|carbon|co2|co₂|footprint)',
            'zero[\s-]*(?:carbon|emissions?)', 'emissions?[\s-]*free', '(?:carbon|co2|co₂|climate)[\s-]*(?:offset(?:ting)?|compensated)',
            'offset (?:by|through) (?:carbon|co2|co₂|climate)', 'compensated for (?:\p{L}+ ){0,2}(?:carbon|co2|co₂|climate|greenhouse|emissions?)',
            // fr
            'neutres? en (?:carbone|co2|co₂)', 'neutralité (?:carbone|climatique)', 'climatiquement neutres?', 'carboneutr\p{L}*',
            'compensée?s? (?:en )?(?:carbone|co2|co₂)', 'zéro (?:carbone|émissions?)', 'bilan carbone neutre', 'neutralité (?:co2|co₂)', 'neutres? pour le climat', 'sans émissions?',
            'émissions? compensée?s?', 'compens\p{L}* (?:\p{L}+ ){0,2}(?:émissions?|carbone|co2|co₂|empreinte)',
            // de
            'klimaneutral\p{L}*', 'klimapositiv\p{L}*', '(?:co2|co₂)[\s-]*(?:neutral|frei)\p{L}*', '(?:co2|co₂|klima)[\s-]*kompensiert\p{L}*',
            'emissionsfrei\p{L}*', 'emissionsneutral\p{L}*', 'netto[\s-]*null', 'treibhausgasneutral\p{L}*', 'thg[\s-]*neutral\p{L}*',
            'kohlenstoffneutral\p{L}*', '(?:co2|co₂)[\s-]*ausgeglichen\p{L}*', 'kompensier\p{L}* (?:\p{L}+ ){0,2}(?:emissionen|co2|co₂|fußabdruck)',
            // es
            'neutr[oa]s? en (?:carbono|co2|co₂)', 'climáticamente neutr[oa]s?', 'neutralidad (?:climática|de carbono|en carbono)',
            'compensad[oa]s? (?:en )?(?:carbono|co2|co₂)', 'cero emisiones', 'carbono[\s-]*neutr(?:al|[oa])s?', 'sin emisiones',
            'libre de emisiones', 'emisiones netas cero', 'huella de carbono cero', 'compens\p{L}* (?:\p{L}+ ){0,2}(?:emisiones|carbono|co2|co₂|huella)',
            // it
            'neutral[ei] (?:in|per (?:il|le)) (?:carbonio|co2|co₂|emissioni)', 'neutr[oaie] in (?:carbonio|co2|co₂)',
            'climaticamente neutr(?:al)?[oaie]', 'neutralità (?:climatica|carbonica)', 'impatto (?:climatico )?zero', 'a emissioni zero',
            'emissioni zero', 'zero emissioni', 'emissioni nette zero', 'emissioni compensate', 'compensat[oaie] (?:in )?(?:co2|co₂|carbonio)',
            'compens\p{L}* (?:\p{L}+ ){0,2}(?:emissioni|carbonio|co2|co₂)',
            // nl
            'klimaatneutra(?:al|le)', '(?:co2|co₂)[\s-]*neutra(?:al|le)', '(?:co2|co₂|klimaat)[\s-]*gecompenseerd\p{L}*', 'emissievrij\p{L}*',
            'netto[\s-]*nul', 'uitstootvrij\p{L}*', 'compense(?:er|r)\p{L}* (?:\p{L}+ ){0,2}(?:uitstoot|co2|co₂)',
            // pl
            'neutraln\p{L}* (?:klimatycznie|węglowo|pod względem emisji)', 'neutralność (?:klimatyczn\p{L}*|węglow\p{L}*)',
            'zerow\p{L}* (?:emisj\p{L}*|ślad\p{L}* węglow\p{L}*)', 'skompensowan\p{L}* (?:emisj\p{L}*|co2|co₂|ślad\p{L}*)', 'zeroemisyjn\p{L}*', 'bezemisyjn\p{L}*',
        ],
    ];

    /**
     * "Colour + noun" eco phrases ("green product", "produit vert"): generic claims, unless the
     * product name, attributes or options give that colour. Other colour claims ("go green",
     * "greenest", "plus vert") are never exempted.
     */
    public const COLOUR_PHRASES = [
        'green (?:product|choice|alternative|option|solution|living|lifestyle|packaging|credentials|brand|company|energy|shopping)s?',
        '(?:produit|choix|alternative|geste|solution|emballage|achat)s? verte?s?',
        'grün(?:e[snrm]?)? (?:produkt|wahl|alternative|lösung|verpackung|energie)\p{L}*',
        '(?:producto|opción|alternativa|elección|solución)e?s? verdes?',
        '(?:prodott|scelt|alternativ|soluzion)[oaie] verd[ei]',
        'groene? (?:keuze|product|alternatief|oplossing|verpakking)\p{L}*',
        'zielon\p{L}* (?:wybór|produkt|alternatyw\p{L}*|rozwiązani\p{L}*)',
    ];

    /** Bare "eco" words, claims unless they name an echo (es/it) or an appliance programme. */
    private const BARE_ECO = ['eco', 'éco', 'eko'];

    private const TECHNICAL_ECO_BEFORE = '/(?:\b(?:de|del|della|dell|l|d|el|lo|sin|senza|con|mode|modo|modus|programme|program|programm|programa|programma|stand|setting|réglage|touche|bouton|button|taste)\s*[\'’]?\s*)$/iu';

    private const TECHNICAL_ECO_AFTER = '/^\s*(?:\d|mode|modus|modo|program\p{L}*|setting|cycle|wash|lavage|wäsche|lavaggio|lavado|plus\b|button|knop|taste)/iu';

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
        'zielony' => 'zielon(?:y|a|ą|e|ę|ego|ej|ym|ych|ymi|emu)',
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

    /** Value starting with a denial ("no" followed by a registration number is not one). */
    private const NEGATIVE_START = '/^(?:no(?!\s*[.:]?\s*\S*\d)|none|non|not|n\/a|false|nope|negative|nein|nicht|kein\p{L}*|ohne|without|aucun\p{L}*|pas|sans|sin|ningun\p{L}*|ningún|nessun\p{L}*|senza|geen|niet|zonder|brak|nie|bez|never|jamais|nie)(?![\p{L}\p{N}])/iu';

    /** Value ending with a denial ("EU Ecolabel: no"). */
    private const NEGATIVE_END = '/(?<![\p{L}\p{N}])(?:no|non|not|none|nope|nein|nicht|aucun\p{L}*|nee|nie|brak|false|n\/a)[^\p{L}\p{N}]*$/iu';

    /** A denial or qualifier within the three words before the label name. */
    private const NEGATION_BEFORE_LABEL = '/(?<![\p{L}\p{N}])(?:no|not|non|never|without|lacks?|lacking|except|excluding|former(?:ly)?|ex|candidate|aiming|applying|applied|pending|sans|pas|jamais|ancien(?:ne)?|ohne|kein\p{L}*|nicht|ehemal\p{L}*|sin|nunca|antigu[oa]|senza|mai|ex|zonder|geen|niet|voormalig\p{L}*|bez|nie|były|była)(?![\p{L}\p{N}])/iu';

    /** A status anywhere in the value that withdraws or postpones the label or property. */
    private const NEGATIVE_STATUS = '/(?<![\p{L}\p{N}])(?:expired|expir\p{L}*|withdrawn|retiré\p{L}*|refused|rejected|denied|refusé\p{L}*|lapsed|revoked|suspend\p{L}*|lost|pending|tbd|to be (?:confirmed|determined)|in progress|under (?:test|review|assessment)|untested|unverified|failed|fail|échoué|candidate|candidat\p{L}*|kandidat\p{L}*|kandydat\p{L}*|application|applied|requested|en cours|en attente|demandée?|beantragt|abgelaufen|zurückgezogen|abgelehnt|nicht bestanden|in attesa|scadut\p{L}*|ritirat\p{L}*|respint\p{L}*|en trámite|solicitad\p{L}*|caducad\p{L}*|retirad\p{L}*|rechazad\p{L}*|aangevraagd|verlopen|ingetrokken|afgewezen|w trakcie|wygasł\p{L}*|wycofan\p{L}*|odrzucon\p{L}*|unknown|inconnu\p{L}*|unbekannt|desconocid\p{L}*|sconosciut\p{L}*|onbekend|nieznan\p{L}*)(?![\p{L}\p{N}])/iu';

    /** Qualifiers that make a biodegradability or compostability statement partial. */
    private const PARTIAL = '/(?<![\p{L}\p{N}])(?:partial\p{L}*|partly|in part|except|excluding|excluded|exclu\p{L}*|ausgenommen|excluid\p{L}*|esclus\p{L}*|uitgezonderd|wyłączeniem|only|but|partiel\p{L}*|sauf|seulement|uniquement|teils|teilweise|außer|nur|parcial\p{L}*|excepto|salvo|solo|parzial\p{L}*|tranne|eccetto|gedeeltelijk|behalve|alleen|częściow\p{L}*|oprócz|tylko|\d+ (?:of|sur|von|de|di|van|z) \d+)(?![\p{L}\p{N}])/iu';

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
     * Matches of every pattern; a match inside an overlapping one ("eco" in "eco-friendly") is
     * dropped.
     *
     * @return list<array{claim: string, type: string}>
     */
    private function find(string $text, ProductFacts $facts): array
    {
        $patterns = self::CLAIMS;
        $patterns[self::TYPE_GENERIC] = [...$patterns[self::TYPE_GENERIC], ...self::COLOUR_PHRASES];
        $colourPhrases = array_flip(self::COLOUR_PHRASES);

        $candidates = [];
        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match_all(self::wrap($pattern), $text, $matches, \PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($matches[0] as [$match, $offset]) {
                        if (isset($colourPhrases[$pattern]) && $this->isProductColour($match, $facts)) {
                            continue;
                        }
                        if (in_array(mb_strtolower($match), self::BARE_ECO, true) && self::isTechnicalEco($text, $offset, strlen($match))) {
                            continue;
                        }
                        $candidates[] = ['claim' => $match, 'type' => $type, 'start' => $offset, 'end' => $offset + strlen($match)];
                    }
                }
            }
        }

        // Neutrality / offsetting claims (always removed) win over overlapping claims, then the longest.
        usort($candidates, static fn (array $a, array $b): int => [
            self::TYPE_CARBON_NEUTRAL === $b['type'], $b['end'] - $b['start'], $a['start'],
        ] <=> [
            self::TYPE_CARBON_NEUTRAL === $a['type'], $a['end'] - $a['start'], $b['start'],
        ]);
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

    /** "cancelación de eco", "cancellazione dell'eco", "Programme Eco 40-60", "mode Eco". */
    private static function isTechnicalEco(string $text, int $offset, int $length): bool
    {
        $before = substr($text, max(0, $offset - 20), min(20, $offset));
        $after = substr($text, $offset + $length, 20);

        return 1 === preg_match(self::TECHNICAL_ECO_BEFORE, $before) || 1 === preg_match(self::TECHNICAL_ECO_AFTER, $after);
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

            // Compound colour names count too: Dunkelgrün, Olivgrün, Donkergroen.
            if (1 === preg_match(self::wrap('\p{L}*(?:' . $root . ')'), $productText)) {
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
            // A value starting with a denial, or withdrawn / postponed, never substantiates.
            if ('' === $value || 1 === preg_match(self::NEGATIVE_START, $value) || 1 === preg_match(self::NEGATIVE_END, $value) ||
                1 === preg_match(self::NEGATIVE_STATUS, $value)) {
                continue;
            }
            $affirmative = 1 === preg_match(self::AFFIRMATIVE_VALUE, $value);

            $supports = match ($type) {
                self::TYPE_GENERIC => in_array($code, $this->ecolabelAttributes, true) &&
                    ($this->namesRecognisedLabelAffirmatively($value) ||
                        ($affirmative && $this->namesRecognisedLabel(mb_strtolower($attribute['name']) . ' ' . str_replace('_', ' ', $code)))),
                self::TYPE_BIODEGRADABLE => in_array($code, $this->biodegradableAttributes, true) &&
                    1 !== preg_match(self::PARTIAL, $value) &&
                    ($affirmative || 1 === preg_match(self::COMPOSTABILITY_STANDARDS, $value)),
                default => false,
            };

            if ($supports) {
                return $attribute['name'] . ': ' . $attribute['value'];
            }
        }

        return null;
    }

    /** The value names a recognised label with no denial or qualifier in the three words before it. */
    private function namesRecognisedLabelAffirmatively(string $value): bool
    {
        foreach ($this->recognisedLabels as $label) {
            if (preg_match_all(self::wrap(preg_quote($label, '/')), $value, $matches, \PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [, $offset]) {
                    $words = preg_split('/[^\p{L}\p{N}]+/u', substr($value, 0, $offset), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
                    if (1 !== preg_match(self::NEGATION_BEFORE_LABEL, implode(' ', array_slice($words, -3)))) {
                        return true;
                    }
                }
            }
        }

        return false;
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
