<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion;

/**
 * A suggestion cannot be generated or decided. The message is a translation key of the
 * "flashes" domain, shown to the admin user.
 */
final class SuggestionException extends \RuntimeException
{
    /** @param array<string, string|int> $parameters */
    public function __construct(
        string $translationKey,
        private readonly array $parameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($translationKey, 0, $previous);
    }

    /** @return array<string, string|int> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
