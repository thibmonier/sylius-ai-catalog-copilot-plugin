<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Psr\Container\ContainerInterface;

/**
 * Generator chosen at runtime, when the configured type is an environment variable
 * (e.g. TMONIER_AI_GENERATOR=demo|platform). The platform generator is only created when
 * used, so the demo mode never needs the provider credentials.
 */
final class SwitchableTextGenerator implements TextGeneratorInterface
{
    public function __construct(
        private readonly string $type,
        private readonly TextGeneratorInterface $demoGenerator,
        private readonly ContainerInterface $platformGenerators,
    ) {
    }

    public function generate(GenerationRequest $request): GeneratedText
    {
        return $this->current()->generate($request);
    }

    public function getType(): string
    {
        return PlatformTextGenerator::TYPE === $this->type ? PlatformTextGenerator::TYPE : DemoTextGenerator::TYPE;
    }

    private function current(): TextGeneratorInterface
    {
        if (PlatformTextGenerator::TYPE !== $this->type) {
            return $this->demoGenerator;
        }

        try {
            $generator = $this->platformGenerators->get(PlatformTextGenerator::TYPE);
        } catch (\Throwable $exception) {
            // Typically a missing API key environment variable. The message never holds a secret.
            throw new PlatformUnavailableException(sprintf('The AI platform could not be created (%s).', $exception::class), 0, $exception);
        }
        if (!$generator instanceof TextGeneratorInterface) {
            throw new PlatformUnavailableException('The AI platform generator is not a text generator.');
        }

        return $generator;
    }
}
