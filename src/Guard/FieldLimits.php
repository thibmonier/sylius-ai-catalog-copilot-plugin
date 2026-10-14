<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;

/**
 * Length limits (in characters) of each field. The meta description defaults to 50-160
 * characters, the size search engines display; meta keywords fit the 255 characters column.
 */
final readonly class FieldLimits
{
    public const DEFAULTS = [
        ProductField::DESCRIPTION => ['min' => 0, 'max' => 5000],
        ProductField::SHORT_DESCRIPTION => ['min' => 0, 'max' => 1000],
        ProductField::META_DESCRIPTION => ['min' => 50, 'max' => 160],
        ProductField::META_KEYWORDS => ['min' => 0, 'max' => 255],
    ];

    /** @var array<string, array{min: int, max: int}> */
    private array $limits;

    /** @param array<string, array{min?: int, max?: int}> $limits */
    public function __construct(array $limits = [])
    {
        $merged = self::DEFAULTS;
        foreach ($limits as $field => $limit) {
            if (isset($merged[$field])) {
                $merged[$field] = array_merge($merged[$field], $limit);
            }
        }
        // Columns of meta fields are VARCHAR(255): never allow more.
        foreach ([ProductField::META_DESCRIPTION, ProductField::META_KEYWORDS] as $field) {
            $merged[$field]['max'] = min(255, $merged[$field]['max']);
        }

        $this->limits = $merged;
    }

    public function minLength(string $field): int
    {
        return $this->limits[$field]['min'] ?? 0;
    }

    public function maxLength(string $field): int
    {
        return $this->limits[$field]['max'] ?? 5000;
    }
}
