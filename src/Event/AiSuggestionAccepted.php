<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Event;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

/**
 * Dispatched after an accepted suggestion was written on the product and flushed.
 */
final readonly class AiSuggestionAccepted
{
    public function __construct(
        public AiSuggestionInterface $suggestion,
        public ProductInterface $product,
        public ?string $previousText,
        public string $newText,
        public ?AdminUserInterface $acceptedBy,
    ) {
    }
}
