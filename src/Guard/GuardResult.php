<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

/**
 * @phpstan-import-type Warning from \Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface
 */
final readonly class GuardResult
{
    /** @param list<Warning> $warnings */
    public function __construct(
        public string $text,
        public array $warnings = [],
    ) {
    }
}
