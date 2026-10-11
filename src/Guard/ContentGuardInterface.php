<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Guard;

use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;

interface ContentGuardInterface
{
    /** Cleans a generated text before it is stored as a suggestion. */
    public function apply(string $field, string $text, ProductFacts $facts): GuardResult;
}
