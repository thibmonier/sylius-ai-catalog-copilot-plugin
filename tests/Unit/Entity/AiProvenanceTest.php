<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenance;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;

final class AiProvenanceTest extends TestCase
{
    public function testItRecordsTheSuggestionAndDetectsHumanEdits(): void
    {
        $suggestion = new AiSuggestion();
        $suggestion->setField('meta_description');
        $suggestion->setGenerator('platform');
        $suggestion->setModel('mistral-small-latest');
        $suggestion->setCreatedAt(new \DateTimeImmutable('2026-10-01 09:00:00'));

        $provenance = new AiProvenance();
        $provenance->record($suggestion, 'A linen shirt.', new \DateTimeImmutable('2026-10-02 10:00:00'));

        self::assertSame('meta_description', $provenance->getField());
        self::assertSame('mistral-small-latest', $provenance->getModel());
        self::assertSame('platform', $provenance->getGenerator());
        self::assertSame($suggestion, $provenance->getSuggestion());
        self::assertSame('2026-10-01', $provenance->getGeneratedAt()->format('Y-m-d'));
        self::assertSame('2026-10-02', $provenance->getAcceptedAt()->format('Y-m-d'));
        self::assertSame(hash('sha256', 'A linen shirt.'), $provenance->getContentHash());
        self::assertTrue($provenance->matches('A linen shirt.'));
        self::assertFalse($provenance->matches('A linen shirt, rewritten.'));
        self::assertFalse($provenance->matches(null));
    }
}
