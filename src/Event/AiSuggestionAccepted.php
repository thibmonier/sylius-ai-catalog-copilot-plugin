<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Event;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

/**
 * Dispatched after an accepted suggestion was written on the product and flushed.
 */
final class AiSuggestionAccepted
{
    public function __construct(
        public readonly AiSuggestionInterface $suggestion,
        public readonly ProductInterface $product,
        public readonly ?string $previousText,
        public readonly string $newText,
        public readonly ?AdminUserInterface $acceptedBy,
    ) {
    }
}
