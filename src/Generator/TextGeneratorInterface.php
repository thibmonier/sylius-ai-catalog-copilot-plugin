<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

/**
 * The single seam between the plugin and a text generation backend. Production code uses
 * PlatformTextGenerator (Symfony AI); the demo and the tests use DemoTextGenerator.
 */
interface TextGeneratorInterface
{
    /** @throws GenerationFailedException */
    public function generate(GenerationRequest $request): GeneratedText;

    /** Generator type stored on suggestions: "demo", "platform" or "service". */
    public function getType(): string;
}
