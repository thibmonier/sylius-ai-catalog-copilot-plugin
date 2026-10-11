<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Twig;

use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;
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
            new TwigFunction('tmonier_ai_copilot_is_demo', fn (): bool => DemoTextGenerator::TYPE === $this->generatorType),
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
        if (null === $product || null === $product->getId()) {
            return [];
        }

        return $this->suggestionRepository->findPendingForProduct($product);
    }

    public function provenance(?ProductInterface $product, string $field, ?string $localeCode = null): ?ProvenanceView
    {
        return null === $product ? null : $this->provenanceProvider->get($product, $field, $localeCode);
    }

    /** @return array<string, ProvenanceView> */
    public function allProvenances(?ProductInterface $product, ?string $localeCode = null): array
    {
        return null === $product ? [] : $this->provenanceProvider->all($product, $localeCode);
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
        $html = htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        if ([] !== $claims) {
            usort($claims, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(
                static fn (string $claim): string => preg_quote(htmlspecialchars($claim, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), '/'),
                $claims,
            )) . ')(?![\p{L}\p{N}])/iu';
            $html = (string) preg_replace($pattern, '<mark class="tmonier-ai-copilot-claim" data-test-tmonier-ai-copilot-claim>$1</mark>', $html);
        }

        return nl2br($html);
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
        if (null === $provenance) {
            return '';
        }

        $attributes = [];
        foreach ($provenance->toDataAttributes() as $name => $value) {
            $attributes[] = sprintf('%s="%s"', $name, htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        }

        return implode(' ', $attributes);
    }
}
