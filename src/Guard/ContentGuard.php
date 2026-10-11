<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

/**
 * Post-processing of every generated text:
 *  1. plain text only (Sylius renders product texts escaped): markup goes through HtmlSanitizer,
 *     scripts and styles are dropped with their content;
 *  2. unsubstantiated environmental claims are flagged to the reviewer (default) or removed
 *     (sentence or keyword); carbon / climate neutrality and offsetting claims are always removed;
 *  3. length limits: truncated at a word boundary, flagged when too short.
 *
 * @phpstan-import-type Warning from \Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface
 */
final class ContentGuard implements ContentGuardInterface
{
    public const CLAIMS_REMOVE = 'remove';

    public const CLAIMS_FLAG = 'flag';

    /** Elements removed with their content; every other element is removed but its text is kept. */
    private const DROPPED_ELEMENTS = [
        'applet', 'audio', 'base', 'button', 'canvas', 'embed', 'form', 'frame', 'frameset', 'head', 'iframe',
        'img', 'input', 'link', 'math', 'meta', 'noembed', 'noframes', 'noscript', 'object', 'option', 'param',
        'picture', 'script', 'select', 'source', 'style', 'svg', 'template', 'textarea', 'title', 'track', 'video',
    ];

    private readonly HtmlSanitizerInterface $sanitizer;

    public function __construct(
        private readonly EnvironmentalClaimDetector $claimDetector,
        private readonly FieldLimits $limits,
        private readonly string $claimsMode = self::CLAIMS_FLAG,
        ?HtmlSanitizerInterface $sanitizer = null,
    ) {
        if (null === $sanitizer) {
            $config = (new HtmlSanitizerConfig())->defaultAction(HtmlSanitizerAction::Block);
            foreach (self::DROPPED_ELEMENTS as $element) {
                $config = $config->dropElement($element);
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

        $analysis = $this->claimDetector->analyse($text, $facts);
        $claims = $analysis['unsubstantiated'];
        foreach ($analysis['substantiated'] as $claim) {
            $warnings[] = [
                'code' => 'environmental_claim_substantiated',
                'params' => ['claim' => $claim['claim'], 'type' => $claim['type'], 'evidence' => $claim['evidence']],
            ];
        }
        // Carbon / climate neutrality and offsetting claims are banned outright (Annex I point 4c):
        // always removed. Other claims are removed in "remove" mode, flagged to the reviewer otherwise.
        $removed = [];
        foreach ($claims as $claim) {
            $remove = self::CLAIMS_REMOVE === $this->claimsMode || EnvironmentalClaimDetector::TYPE_CARBON_NEUTRAL === $claim['type'];
            if ($remove) {
                $removed[] = $claim['claim'];
            }
            $warnings[] = [
                'code' => $remove ? 'environmental_claim_removed' : 'environmental_claim_flagged',
                'params' => ['claim' => $claim['claim'], 'type' => $claim['type']],
            ];
        }
        if ([] !== $removed) {
            $text = $this->removeClaims($field, $text, $removed);
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
        // Markdown code fences some models wrap their answer in.
        $text = (string) preg_replace('/^[ \t]*```[\w-]*[ \t]*$/mu', '', $text);
        // Raw-text elements first: the HTML parser may move them out of the body before sanitizing.
        $text = (string) preg_replace('#<(script|style|template|noscript|iframe|object|svg|math|textarea|title)\b[^>]*>.*?</\1\s*>#isu', '', $text);
        // Keep the structure of HTML answers as line breaks before the markup is removed.
        $text = (string) preg_replace('#<(table|ul|ol|dl|pre|figure)\b#i', "\n\n$0", $text);
        $text = (string) preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = (string) preg_replace('#</(p|div|h[1-6]|li|blockquote|section|article|main|header|footer|aside|table|ul|ol|dl|pre|figure)>#i', "$0\n\n", $text);
        $text = (string) preg_replace('#</(tr|dt|dd|caption)>#i', "$0\n", $text);
        $text = (string) preg_replace('#</(td|th)>#i', '$0 ', $text);
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
