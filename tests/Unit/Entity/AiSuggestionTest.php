<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUser;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

final class AiSuggestionTest extends TestCase
{
    public function testANewSuggestionIsPending(): void
    {
        $suggestion = new AiSuggestion();

        self::assertTrue($suggestion->isPending());
        self::assertFalse($suggestion->isTranslation());
        self::assertSame([], $suggestion->getWarnings());
        self::assertNull($suggestion->getDecidedAt());
    }

    public function testItIsDecidedOnce(): void
    {
        $suggestion = new AiSuggestion();
        $admin = new AdminUser();
        $suggestion->accept($admin, new \DateTimeImmutable('2026-10-11 10:00:00'));

        self::assertSame(AiSuggestionInterface::STATUS_ACCEPTED, $suggestion->getStatus());
        self::assertSame($admin, $suggestion->getDecidedBy());

        $this->expectException(\LogicException::class);
        $suggestion->reject(null, new \DateTimeImmutable());
    }

    public function testATranslationKnowsItsSourceLocale(): void
    {
        $suggestion = new AiSuggestion();
        $suggestion->setSourceLocaleCode('en_US');

        self::assertTrue($suggestion->isTranslation());
    }
}
