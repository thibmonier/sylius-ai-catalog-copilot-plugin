<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\Prompt;

final class GenerationRequest
{
    public function __construct(
        public readonly string $field,
        public readonly ProductFacts $facts,
        public readonly Prompt $prompt,
        public readonly ?string $sourceLocaleCode = null,
        public readonly ?string $sourceText = null,
    ) {
    }

    public function isTranslation(): bool
    {
        return null !== $this->sourceLocaleCode;
    }
}
