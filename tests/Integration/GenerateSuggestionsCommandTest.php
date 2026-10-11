<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Integration;

use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional\CopilotTestTrait;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

final class GenerateSuggestionsCommandTest extends WebTestCase
{
    use CopilotTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testItGeneratesPendingSuggestionsForTheGivenProductsOnly(): void
    {
        $empty = $this->createProduct('AI_CMD_EMPTY');
        $filled = $this->createProduct('AI_CMD_FILLED', ['description' => 'Already written']);

        $tester = $this->command();
        $tester->execute(['--field' => 'description', '--locale' => 'en_US', '--only-empty' => true, '--product' => ['AI_CMD_EMPTY', 'AI_CMD_FILLED']]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 suggestion(s) generated, 0 failure(s).', $tester->getDisplay());
        self::assertCount(1, $this->suggestionsOf($empty));
        self::assertCount(0, $this->suggestionsOf($filled));
        self::assertNull($this->reload($empty)->getTranslations()->get('en_US')?->getDescription());

        // A second run skips products that already have a pending suggestion.
        $tester->execute(['--field' => 'description', '--locale' => 'en_US', '--product' => ['AI_CMD_EMPTY', 'AI_CMD_FILLED']]);
        self::assertStringContainsString('1 suggestion(s) generated', $tester->getDisplay());
        self::assertCount(1, $this->suggestionsOf($empty));
        self::assertCount(1, $this->suggestionsOf($filled));
    }

    public function testDryRunListsTheProductsWithoutGenerating(): void
    {
        $product = $this->createProduct('AI_CMD_DRY');

        $tester = $this->command();
        $tester->execute(['--field' => 'meta_description', '--locale' => 'en_US', '--product' => ['AI_CMD_DRY'], '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('AI_CMD_DRY', $tester->getDisplay());
        self::assertStringContainsString('1 product(s) would get a "meta_description" suggestion in en_US', $tester->getDisplay());
        self::assertCount(0, $this->suggestionsOf($product));
    }

    public function testTranslationRunsOnlyProductsWithASourceText(): void
    {
        $withSource = $this->createProduct('AI_CMD_SRC', ['description' => 'A linen shirt.']);
        $withoutSource = $this->createProduct('AI_CMD_NOSRC');

        $tester = $this->command();
        $tester->execute(['--field' => 'description', '--locale' => 'fr_FR', '--source-locale' => 'en_US', '--product' => ['AI_CMD_SRC', 'AI_CMD_NOSRC']]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $suggestions = $this->suggestionsOf($withSource);
        self::assertCount(1, $suggestions);
        self::assertSame('[fr_FR] A linen shirt.', $suggestions[0]->getSuggestedText());
        self::assertCount(0, $this->suggestionsOf($withoutSource));
    }

    public function testTheLimitIsApplied(): void
    {
        $this->createProduct('AI_CMD_LIMIT_1');
        $this->createProduct('AI_CMD_LIMIT_2');

        $tester = $this->command();
        $tester->execute(['--field' => 'meta_keywords', '--locale' => 'en_US', '--product' => ['AI_CMD_LIMIT_1', 'AI_CMD_LIMIT_2'], '--limit' => '1']);

        self::assertStringContainsString('1 suggestion(s) generated', $tester->getDisplay());
    }

    public function testInvalidOptionsAreRefused(): void
    {
        $tester = $this->command();

        self::assertSame(Command::INVALID, $tester->execute(['--field' => 'name', '--locale' => 'en_US']));
        self::assertSame(Command::INVALID, $tester->execute(['--field' => 'description']));
    }

    private function command(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester((new Application($kernel))->find('tmonier:ai-copilot:generate'));
    }

    /** @return list<AiSuggestionInterface> */
    private function suggestionsOf(ProductInterface $product): array
    {
        /** @var list<AiSuggestionInterface> $suggestions */
        $suggestions = $this->entityManager()->getRepository(AiSuggestionInterface::class)->findBy(['product' => $this->idOf($product)]);

        return $suggestions;
    }
}
