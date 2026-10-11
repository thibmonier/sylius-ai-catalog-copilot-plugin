<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit;

use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GeneratedText;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationFailedException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\TextGeneratorInterface;

/** Fake at our own interface level: returns a scripted text and records the requests. */
final class FakeTextGenerator implements TextGeneratorInterface
{
    /** @var list<GenerationRequest> */
    public array $requests = [];

    public function __construct(
        private readonly string $text = 'A generated text.',
        private readonly bool $fail = false,
    ) {
    }

    public function generate(GenerationRequest $request): GeneratedText
    {
        $this->requests[] = $request;
        if ($this->fail) {
            throw new GenerationFailedException('Platform down.');
        }

        return new GeneratedText($this->text, 'fake-model', 100, 20);
    }

    public function getType(): string
    {
        return 'platform';
    }
}
