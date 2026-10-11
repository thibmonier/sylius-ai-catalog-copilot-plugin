<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

/**
 * Post-processing of every generated text:
 *  1. plain text only (Sylius renders product texts escaped): markup goes through HtmlSanitizer,
 *     scripts and styles are dropped with their content;
 *  2. unsubstantiated environmental claims are removed (sentence or keyword) or only flagged;
 *  3. length limits: truncated at a word boundary, flagged when too short.
 *
 * @phpstan-import-type Warning from \Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface
 */
final class ContentGuard implements ContentGuardInterface
{
    public const CLAIMS_REMOVE = 'remove';

    public const CLAIMS_FLAG = 'flag';

    private const TEXT_ELEMENTS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'li',
        'mark', 'ol', 'p', 'section', 'small', 'span', 'strong', 'sub', 'sup', 'u', 'ul',
    ];

    private readonly HtmlSanitizerInterface $sanitizer;

    public function __construct(
        private readonly EnvironmentalClaimDetector $claimDetector,
        private readonly FieldLimits $limits,
        private readonly string $claimsMode = self::CLAIMS_REMOVE,
        ?HtmlSanitizerInterface $sanitizer = null,
    ) {
        if (null === $sanitizer) {
            $config = new HtmlSanitizerConfig();
            foreach (self::TEXT_ELEMENTS as $element) {
                $config = $config->blockElement($element);
            }
            $sanitizer = new HtmlSanitizer($config->withMaxInputLength(-1));
        }
        $this->sanitizer = $sanitizer;
    }

    public function apply(string $field, string $text, ProductFacts $facts): GuardResult
    {
        $warnings = [];
        $text = $this->toPlainText($text, ProductField::isSingleLine($field));
        if (ProductField::META_KEYWORDS === $field) {
            $text = $this->normalizeKeywords($text);
        }

        $claims = $this->claimDetector->findUnsubstantiated($text, $facts->attributesText());
        foreach ($claims as $claim) {
            $warnings[] = [
                'code' => self::CLAIMS_REMOVE === $this->claimsMode ? 'environmental_claim_removed' : 'environmental_claim_flagged',
                'params' => ['claim' => $claim['claim'], 'type' => $claim['type']],
            ];
        }
        if ([] !== $claims && self::CLAIMS_REMOVE === $this->claimsMode) {
            $text = $this->removeClaims($field, $text, array_column($claims, 'claim'));
        }

        if (ProductField::META_KEYWORDS === $field) {
            $text = $this->normalizeKeywords($text);
        }

        $max = $this->limits->maxLength($field);
        if ($max > 0 && mb_strlen($text) > $max) {
            $text = $this->truncate($text, $max, ProductField::META_KEYWORDS === $field);
            $warnings[] = ['code' => 'truncated', 'params' => ['max' => $max]];
        }

        $min = $this->limits->minLength($field);
        if ('' !== $text && mb_strlen($text) < $min) {
            $warnings[] = ['code' => 'too_short', 'params' => ['min' => $min, 'length' => mb_strlen($text)]];
        }

        return new GuardResult($text, $warnings);
    }

    private function toPlainText(string $text, bool $singleLine): string
    {
        // Keep the structure of HTML answers as line breaks before the markup is removed.
        $text = (string) preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = (string) preg_replace('#</(p|div|h[1-6]|li|blockquote|section)>#i', "$0\n\n", $text);
        $text = (string) preg_replace('#<li[^>]*>#i', '$0- ', $text);
        // Markdown emphasis and headings some models add anyway.
        $text = (string) preg_replace('/(\*\*|__)(.+?)\1/u', '$2', $text);
        $text = (string) preg_replace('/^#{1,6}\s+/mu', '', $text);

        $text = html_entity_decode($this->sanitizer->sanitize($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if ($singleLine) {
            $text = (string) preg_replace('/\s+/u', ' ', $text);
        } else {
            $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
            $text = (string) preg_replace('/ *\n */u', "\n", $text);
            $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);
        }

        return $this->trimQuotes(trim($text));
    }

    private function trimQuotes(string $text): string
    {
        foreach (['"' => '"', '“' => '”', '«' => '»', '\'' => '\''] as $open => $close) {
            if (mb_strlen($text) > 2 && str_starts_with($text, $open) && str_ends_with($text, $close) &&
                !str_contains(mb_substr($text, 1, -1), $open)) {
                return trim(mb_substr($text, 1, -1));
            }
        }

        return $text;
    }

    /** @param list<string> $claims */
    private function removeClaims(string $field, string $text, array $claims): string
    {
        $containsClaim = function (string $part) use ($claims): bool {
            foreach ($claims as $claim) {
                if ($this->claimDetector->contains($part, $claim)) {
                    return true;
                }
            }

            return false;
        };

        if (ProductField::META_KEYWORDS === $field) {
            $keywords = array_filter(explode(',', $text), static fn (string $keyword): bool => !$containsClaim($keyword));

            return implode(',', $keywords);
        }

        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $sentences = preg_split('/(?<=[.!?…])\s+/u', $line) ?: [$line];
            $kept = array_filter($sentences, static fn (string $sentence): bool => !$containsClaim($sentence));
            $lines[] = trim(implode(' ', $kept));
        }

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", implode("\n", $lines)));
    }

    private function normalizeKeywords(string $text): string
    {
        $keywords = [];
        foreach (preg_split('/[,;\n]+/u', $text) ?: [] as $keyword) {
            $keyword = mb_strtolower(trim($keyword, " \t.-"));
            if ('' !== $keyword) {
                $keywords[$keyword] = $keyword;
            }
        }

        return implode(', ', $keywords);
    }

    private function truncate(string $text, int $max, bool $keywords): string
    {
        if ($keywords) {
            $kept = '';
            foreach (explode(', ', $text) as $keyword) {
                $candidate = '' === $kept ? $keyword : $kept . ', ' . $keyword;
                if (mb_strlen($candidate) > $max) {
                    break;
                }
                $kept = $candidate;
            }

            return $kept;
        }

        $cut = mb_substr($text, 0, $max + 1);
        $lastSpace = mb_strrpos($cut, ' ');
        $cut = false !== $lastSpace && $lastSpace > $max / 2 ? mb_substr($cut, 0, $lastSpace) : mb_substr($text, 0, $max);

        return rtrim($cut, " \t\n,;:-");
    }
}
