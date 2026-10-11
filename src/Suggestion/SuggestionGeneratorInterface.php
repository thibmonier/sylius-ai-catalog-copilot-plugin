<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

interface SuggestionGeneratorInterface
{
    /**
     * Generates and saves a pending suggestion for one field of one product translation.
     * With $sourceLocaleCode, the field is translated from that locale instead of being
     * written from the product facts. Older pending suggestions of the same field are rejected.
     *
     * @throws SuggestionException
     */
    public function generate(
        ProductInterface $product,
        string $field,
        string $localeCode,
        ?string $sourceLocaleCode = null,
        ?AdminUserInterface $requestedBy = null,
    ): AiSuggestionInterface;
}
