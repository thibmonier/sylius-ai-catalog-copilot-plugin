<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\Prompt;

final readonly class GenerationRequest
{
    public function __construct(
        public string $field,
        public ProductFacts $facts,
        public Prompt $prompt,
        public ?string $sourceLocaleCode = null,
        public ?string $sourceText = null,
    ) {
    }

    public function isTranslation(): bool
    {
        return null !== $this->sourceLocaleCode;
    }
}
