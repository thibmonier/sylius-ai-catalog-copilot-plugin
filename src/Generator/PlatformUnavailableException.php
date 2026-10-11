<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

/** The AI platform is not configured (e.g. missing API key) or cannot be created. */
final class PlatformUnavailableException extends GenerationFailedException
{
}
