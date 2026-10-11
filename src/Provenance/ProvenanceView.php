<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Provenance;

final class ProvenanceView
{
    /** The field still holds the accepted AI text. */
    public const STATUS_GENERATED = 'generated';

    /** The field was written from an AI suggestion, then edited by a human. */
    public const STATUS_EDITED = 'edited';

    public function __construct(
        public readonly string $field,
        public readonly string $localeCode,
        public readonly string $status,
        public readonly string $generator,
        public readonly string $model,
        public readonly \DateTimeImmutable $generatedAt,
        public readonly \DateTimeImmutable $acceptedAt,
    ) {
    }

    public function isAiGenerated(): bool
    {
        return self::STATUS_GENERATED === $this->status;
    }

    /** @return array<string, string> machine-readable marking, as HTML data attributes */
    public function toDataAttributes(): array
    {
        return [
            'data-ai-generated' => $this->isAiGenerated() ? 'true' : 'false',
            'data-ai-edited' => $this->isAiGenerated() ? 'false' : 'true',
            'data-ai-model' => $this->model,
            'data-ai-generated-at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
