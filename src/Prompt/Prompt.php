<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Prompt;

final class Prompt
{
    public function __construct(
        public readonly string $system,
        public readonly string $user,
    ) {
    }

    /** sha256 of the messages, stored on the suggestion to trace what was sent. */
    public function hash(): string
    {
        return hash('sha256', $this->system . "\0" . $this->user);
    }
}
