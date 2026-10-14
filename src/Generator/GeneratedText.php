<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

final readonly class GeneratedText
{
    public function __construct(
        public string $text,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {
    }
}
