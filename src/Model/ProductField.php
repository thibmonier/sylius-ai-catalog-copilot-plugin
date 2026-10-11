<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Model;

use Sylius\Component\Core\Model\ProductTranslationInterface;

/**
 * The product translation fields the copilot can write.
 */
final class ProductField
{
    public const DESCRIPTION = 'description';

    public const SHORT_DESCRIPTION = 'short_description';

    public const META_DESCRIPTION = 'meta_description';

    public const META_KEYWORDS = 'meta_keywords';

    public const ALL = [
        self::DESCRIPTION,
        self::SHORT_DESCRIPTION,
        self::META_DESCRIPTION,
        self::META_KEYWORDS,
    ];

    private function __construct()
    {
    }

    public static function isValid(string $field): bool
    {
        return in_array($field, self::ALL, true);
    }

    public static function getValue(ProductTranslationInterface $translation, string $field): ?string
    {
        return match ($field) {
            self::DESCRIPTION => $translation->getDescription(),
            self::SHORT_DESCRIPTION => $translation->getShortDescription(),
            self::META_DESCRIPTION => $translation->getMetaDescription(),
            self::META_KEYWORDS => $translation->getMetaKeywords(),
            default => throw new \InvalidArgumentException(sprintf('Unknown product field "%s".', $field)),
        };
    }

    public static function setValue(ProductTranslationInterface $translation, string $field, ?string $value): void
    {
        match ($field) {
            self::DESCRIPTION => $translation->setDescription($value),
            self::SHORT_DESCRIPTION => $translation->setShortDescription($value),
            self::META_DESCRIPTION => $translation->setMetaDescription($value),
            self::META_KEYWORDS => $translation->setMetaKeywords($value),
            default => throw new \InvalidArgumentException(sprintf('Unknown product field "%s".', $field)),
        };
    }

    /** Doctrine property of the field on ProductTranslation. */
    public static function property(string $field): string
    {
        return match ($field) {
            self::DESCRIPTION => 'description',
            self::SHORT_DESCRIPTION => 'shortDescription',
            self::META_DESCRIPTION => 'metaDescription',
            self::META_KEYWORDS => 'metaKeywords',
            default => throw new \InvalidArgumentException(sprintf('Unknown product field "%s".', $field)),
        };
    }

    /** Fields shown as a single line (no line breaks kept). */
    public static function isSingleLine(string $field): bool
    {
        return self::META_DESCRIPTION === $field || self::META_KEYWORDS === $field;
    }
}
