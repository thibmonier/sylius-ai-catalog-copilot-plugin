<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Suggestion;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\CatalogTrait;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\FakeTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractor;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuard;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGenerator;

final class SuggestionGeneratorTest extends TestCase
{
    use CatalogTrait;

    /** @var list<object> */
    private array $persisted = [];

    private int $flushes = 0;

    /** @var list<AiSuggestionInterface> */
    private array $pending = [];

    public function testItStoresAGuardedPendingSuggestionWithoutTouchingTheProduct(): void
    {
        $generator = new FakeTextGenerator('<p>A linen shirt.</p> It is eco-friendly. Carbon neutral delivery.');
        $product = $this->product(['en_US' => ['name' => 'Linen shirt', 'description' => 'Old text']]);

        $suggestion = $this->suggestionGenerator($generator)->generate($product, ProductField::DESCRIPTION, 'en_US');

        // Flagged by default, neutrality claims always removed.
        self::assertSame("A linen shirt.\n\nIt is eco-friendly.", $suggestion->getSuggestedText());
        self::assertSame('Old text', $suggestion->getOriginalText());
        self::assertSame('Old text', $this->translation($product, 'en_US')->getDescription());
        self::assertTrue($suggestion->isPending());
        self::assertSame($product, $suggestion->getProduct());
        self::assertSame('platform', $suggestion->getGenerator());
        self::assertSame('fake-model', $suggestion->getModel());
        self::assertSame(100, $suggestion->getInputTokens());
        self::assertSame(20, $suggestion->getOutputTokens());
        self::assertSame('2026-10-11 10:00:00', $suggestion->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame($generator->requests[0]->prompt->hash(), $suggestion->getPromptHash());
        self::assertSame(['environmental_claim_flagged', 'environmental_claim_removed'], array_column($suggestion->getWarnings(), 'code'));
        self::assertSame([$suggestion], $this->persisted);
        self::assertSame(1, $this->flushes);
    }

    public function testThePromptContainsTheProductFactsOnly(): void
    {
        $generator = new FakeTextGenerator();

        $this->suggestionGenerator($generator)->generate($this->product(), ProductField::META_KEYWORDS, 'en_US');

        $prompt = $generator->requests[0]->prompt;
        self::assertStringContainsString('Product name: Linen shirt', $prompt->user);
        self::assertStringNotContainsString('@', $prompt->user . $prompt->system);
    }

    public function testATranslationUsesTheSourceLocaleText(): void
    {
        $generator = new FakeTextGenerator('Une chemise en lin.');
        $product = $this->product([
            'en_US' => ['name' => 'Linen shirt', 'description' => 'A linen shirt.'],
            'fr_FR' => ['name' => 'Chemise en lin'],
        ]);

        $suggestion = $this->suggestionGenerator($generator)->generate($product, ProductField::DESCRIPTION, 'fr_FR', 'en_US');

        self::assertSame('en_US', $suggestion->getSourceLocaleCode());
        self::assertTrue($suggestion->isTranslation());
        self::assertNull($suggestion->getOriginalText());
        self::assertSame('A linen shirt.', $generator->requests[0]->sourceText);
        self::assertStringContainsString("<source_text>\nA linen shirt.\n</source_text>", $generator->requests[0]->prompt->user);
    }

    public function testNothingToTranslateIsRefused(): void
    {
        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.empty_source'));

        $this->suggestionGenerator(new FakeTextGenerator())->generate($this->product(['en_US' => ['name' => 'Shirt'], 'fr_FR' => ['name' => 'Chemise']]), ProductField::DESCRIPTION, 'fr_FR', 'en_US');
    }

    public function testOlderPendingSuggestionsOfTheSameFieldAreRejected(): void
    {
        $older = new AiSuggestion();
        $this->pending = [$older];

        $this->suggestionGenerator(new FakeTextGenerator())->generate($this->product(), ProductField::DESCRIPTION, 'en_US');

        self::assertSame(AiSuggestionInterface::STATUS_REJECTED, $older->getStatus());
        self::assertNull($older->getDecidedBy());
    }

    public function testGenerationFailuresAreReported(): void
    {
        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.generation_failed'));

        try {
            $this->suggestionGenerator(new FakeTextGenerator('x', true))->generate($this->product(), ProductField::DESCRIPTION, 'en_US');
        } finally {
            self::assertSame([], $this->persisted);
        }
    }

    public function testATextEmptiedByTheGuardIsRefused(): void
    {
        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.empty_result'));

        $this->suggestionGenerator(new FakeTextGenerator('Carbon neutral!'))->generate($this->product(), ProductField::DESCRIPTION, 'en_US');
    }

    public function testInvalidRequestsAreRefused(): void
    {
        $generator = $this->suggestionGenerator(new FakeTextGenerator());

        try {
            $generator->generate($this->product(), 'name', 'en_US');
            self::fail('Unknown field accepted.');
        } catch (SuggestionException $exception) {
            self::assertSame('tmonier_ai_copilot.error.unknown_field', $exception->getMessage());
            self::assertSame(['%field%' => 'name'], $exception->getParameters());
        }

        $this->expectExceptionObject(new SuggestionException('tmonier_ai_copilot.error.same_locale'));
        $generator->generate($this->product(), ProductField::DESCRIPTION, 'en_US', 'en_US');
    }

    private function suggestionGenerator(FakeTextGenerator $textGenerator): SuggestionGenerator
    {
        $repository = $this->createMock(AiSuggestionRepositoryInterface::class);
        $repository->method('findPendingFor')->willReturnCallback(fn (): array => $this->pending);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $object): void {
            $this->persisted[] = $object;
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });

        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): AiSuggestion => new AiSuggestion());

        return new SuggestionGenerator(
            new ProductFactsExtractor(),
            $this->promptBuilder(),
            $textGenerator,
            new ContentGuard(new EnvironmentalClaimDetector(), new FieldLimits()),
            $factory,
            $repository,
            $entityManager,
            new MockClock('2026-10-11 10:00:00'),
            new NullLogger(),
            'en_US',
        );
    }
}
