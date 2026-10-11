<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion;

use Sylius\Component\Core\Model\AdminUserInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

interface SuggestionApplierInterface
{
    /**
     * Writes the suggested text on the product translation, records its provenance, flushes
     * and dispatches AiSuggestionAccepted. Refused when the field changed since the generation.
     *
     * @throws SuggestionException
     */
    public function accept(AiSuggestionInterface $suggestion, ?AdminUserInterface $decidedBy): void;

    /** @throws SuggestionException */
    public function reject(AiSuggestionInterface $suggestion, ?AdminUserInterface $decidedBy): void;
}
