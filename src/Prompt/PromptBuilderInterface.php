<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Prompt;

use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;

interface PromptBuilderInterface
{
    /** Prompt writing $field in the locale of $facts from the product facts only. */
    public function buildGeneration(string $field, ProductFacts $facts): Prompt;

    /** Prompt translating $sourceText of $field from $sourceLocaleCode to the locale of $facts. */
    public function buildTranslation(string $field, ProductFacts $facts, string $sourceLocaleCode, string $sourceText): Prompt;
}
