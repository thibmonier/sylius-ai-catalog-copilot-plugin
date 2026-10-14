<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Twig;

use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\DependencyInjection\Configuration;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceProviderInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceView;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AiCopilotExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProvenanceProviderInterface $provenanceProvider,
        private readonly AiSuggestionRepositoryInterface $suggestionRepository,
        private readonly string $generatorType,
        private readonly string $sourceLocaleCode,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_ai_copilot_fields', static fn (): array => ProductField::ALL),
            new TwigFunction('tmonier_ai_copilot_is_demo', fn (): bool => PlatformTextGenerator::TYPE !== $this->generatorType && Configuration::GENERATOR_SERVICE !== $this->generatorType),
            new TwigFunction('tmonier_ai_copilot_source_locale', fn (): string => $this->sourceLocaleCode),
            new TwigFunction('tmonier_ai_copilot_pending_suggestions', $this->pendingSuggestions(...)),
            new TwigFunction('tmonier_ai_copilot_provenance', $this->provenance(...)),
            new TwigFunction('tmonier_ai_copilot_all_provenances', $this->allProvenances(...)),
            new TwigFunction('tmonier_ai_copilot_provenance_attributes', $this->provenanceAttributes(...), ['is_safe' => ['html']]),
            new TwigFunction('tmonier_ai_copilot_highlight_claims', $this->highlightClaims(...), ['is_safe' => ['html']]),
            new TwigFunction('tmonier_ai_copilot_flagged_claims', $this->flaggedClaims(...)),
        ];
    }

    /** @return list<AiSuggestionInterface> */
    public function pendingSuggestions(?ProductInterface $product): array
    {
        if (!$product instanceof \Sylius\Component\Core\Model\ProductInterface || null === $product->getId()) {
            return [];
        }

        return $this->suggestionRepository->findPendingForProduct($product);
    }

    public function provenance(?ProductInterface $product, string $field, ?string $localeCode = null): ?ProvenanceView
    {
        return $product instanceof \Sylius\Component\Core\Model\ProductInterface ? $this->provenanceProvider->get($product, $field, $localeCode) : null;
    }

    /** @return array<string, ProvenanceView> */
    public function allProvenances(?ProductInterface $product, ?string $localeCode = null): array
    {
        return $product instanceof \Sylius\Component\Core\Model\ProductInterface ? $this->provenanceProvider->all($product, $localeCode) : [];
    }

    /**
     * Escaped text with line breaks, where every flagged or substantiated environmental claim of
     * the warnings is wrapped in a <mark> for the reviewer.
     *
     * @param list<array{code: string, params?: array<string, string|int>}> $warnings
     */
    public function highlightClaims(string $text, array $warnings): string
    {
        $claims = $this->flaggedClaims($warnings, true);
        if ([] === $claims) {
            return nl2br($this->escape($text));
        }

        // Claims were found on the normalised text: match hyphens, dashes, apostrophes and spaces loosely.
        usort($claims, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $bodies = array_map(static function (string $claim): string {
            $body = preg_quote(EnvironmentalClaimDetector::normalize($claim), '/');
            $body = str_replace(['\\-', '\''], ['[\-\x{2010}-\x{2015}\x{2212}]', '[\'’‘ʼ`]'], $body);

            return (string) preg_replace('/\s+/u', '[\s\x{00A0}\x{202F}]+', $body);
        }, $claims);
        $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', $bodies) . ')(?![\p{L}\p{N}])/iu';

        $parts = preg_split($pattern, $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $index => $part) {
            $html .= 1 === $index % 2
                ? '<mark class="tmonier-ai-copilot-claim" data-test-tmonier-ai-copilot-claim>' . $this->escape($part) . '</mark>'
                : $this->escape($part);
        }

        return nl2br($html);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * Claims flagged to the reviewer (kept in the text).
     *
     * @param list<array{code: string, params?: array<string, string|int>}> $warnings
     *
     * @return list<string>
     */
    public function flaggedClaims(array $warnings, bool $withSubstantiated = false): array
    {
        $codes = $withSubstantiated ? ['environmental_claim_flagged', 'environmental_claim_substantiated'] : ['environmental_claim_flagged'];
        $claims = [];
        foreach ($warnings as $warning) {
            $claim = $warning['params']['claim'] ?? null;
            if (in_array($warning['code'], $codes, true) && is_string($claim) && '' !== $claim) {
                $claims[$claim] = $claim;
            }
        }

        return array_values($claims);
    }

    /** HTML data attributes marking an AI-written field, e.g. data-ai-generated="true". */
    public function provenanceAttributes(?ProductInterface $product, string $field, ?string $localeCode = null): string
    {
        $provenance = $this->provenance($product, $field, $localeCode);
        if (!$provenance instanceof \Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceView) {
            return '';
        }

        $attributes = [];
        foreach ($provenance->toDataAttributes() as $name => $value) {
            $attributes[] = sprintf('%s="%s"', $name, htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        }

        return implode(' ', $attributes);
    }
}
