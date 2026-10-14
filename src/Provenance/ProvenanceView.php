<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Provenance;

use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;

final readonly class ProvenanceView
{
    /** The field still holds the accepted AI text. */
    public const STATUS_GENERATED = 'generated';

    /** The field was written from an AI suggestion, then edited by a human. */
    public const STATUS_EDITED = 'edited';

    public function __construct(
        public string $field,
        public string $localeCode,
        public string $status,
        public string $generator,
        public string $model,
        public \DateTimeImmutable $generatedAt,
        public \DateTimeImmutable $acceptedAt,
    ) {
    }

    /**
     * True while the field holds a text written by an AI model. Texts of the demo generator
     * (assembled without any AI) are never reported as AI-generated.
     */
    public function isAiGenerated(): bool
    {
        return self::STATUS_GENERATED === $this->status && !$this->isDemo();
    }

    public function isDemo(): bool
    {
        return DemoTextGenerator::TYPE === $this->generator;
    }

    /** Label key suffix: "demo", "generated" or "edited". */
    public function getLabel(): string
    {
        return $this->isDemo() ? 'demo' : $this->status;
    }

    /** @return array<string, string> machine-readable marking, as HTML data attributes */
    public function toDataAttributes(): array
    {
        return [
            'data-ai-generated' => $this->isAiGenerated() ? 'true' : 'false',
            'data-ai-edited' => self::STATUS_EDITED === $this->status ? 'true' : 'false',
            'data-ai-generator' => $this->generator,
            'data-ai-model' => $this->model,
            'data-ai-generated-at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
