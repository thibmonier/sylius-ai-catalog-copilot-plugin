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
        self::assertNull($this->translation($this->reload($empty), 'en_US')->getDescription());

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
        self::assertSame('en_US', $suggestions[0]->getSourceLocaleCode());
        self::assertStringStartsWith('Découvrez Chemise en lin AI_CMD_SRC.', $suggestions[0]->getSuggestedText());
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
        foreach (['0', '-1', '5x', ''] as $limit) {
            self::assertSame(Command::INVALID, $tester->execute(['--field' => 'description', '--locale' => 'en_US', '--limit' => $limit]), $limit);
        }
        self::assertSame(Command::INVALID, $tester->execute(['--field' => 'description', '--locale' => 'en_US', '--after-id' => 'x']));
    }

    public function testDecidedSuggestionsAreNotRegeneratedUnlessAsked(): void
    {
        $product = $this->createProduct('AI_CMD_DECIDED');
        /** @var \Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface $applier */
        $applier = self::getContainer()->get('tmonier_ai_copilot.suggestion_applier');
        $applier->reject($this->generate($product, 'meta_keywords'), null);

        $tester = $this->command();
        $tester->execute(['--field' => 'meta_keywords', '--locale' => 'en_US', '--product' => ['AI_CMD_DECIDED']]);
        self::assertStringContainsString('No product to process.', $tester->getDisplay());

        $tester->execute(['--field' => 'meta_keywords', '--locale' => 'en_US', '--product' => ['AI_CMD_DECIDED'], '--include-processed' => true]);
        self::assertStringContainsString('1 suggestion(s) generated', $tester->getDisplay());
        self::assertCount(2, $this->suggestionsOf($product));
    }

    public function testTheCursorMovesPastTheProcessedProducts(): void
    {
        $first = $this->createProduct('AI_CMD_CURSOR_1');
        $second = $this->createProduct('AI_CMD_CURSOR_2');
        $codes = ['AI_CMD_CURSOR_1', 'AI_CMD_CURSOR_2'];

        $tester = $this->command();
        $tester->execute(['--field' => 'short_description', '--locale' => 'en_US', '--product' => $codes, '--limit' => '1', '--dry-run' => true]);
        self::assertStringContainsString('AI_CMD_CURSOR_1', $tester->getDisplay());
        self::assertStringNotContainsString('AI_CMD_CURSOR_2', $tester->getDisplay());
        self::assertStringContainsString(sprintf('--after-id=%d', $this->idOf($first)), $tester->getDisplay());

        $tester->execute(['--field' => 'short_description', '--locale' => 'en_US', '--product' => $codes, '--limit' => '1', '--after-id' => (string) $this->idOf($first)]);
        self::assertStringContainsString('1 suggestion(s) generated', $tester->getDisplay());
        self::assertCount(0, $this->suggestionsOf($first));
        self::assertCount(1, $this->suggestionsOf($second));
    }

    public function testWhitespaceOrMarkupOnlyCountsAsEmpty(): void
    {
        $markup = $this->createProduct('AI_CMD_MARKUP', ['description' => " <p>&nbsp;</p>\n "]);
        $blankSource = $this->createProduct('AI_CMD_BLANK_SOURCE', ['description' => '<p> </p>']);

        $tester = $this->command();
        $tester->execute(['--field' => 'description', '--locale' => 'en_US', '--only-empty' => true, '--product' => ['AI_CMD_MARKUP']]);
        self::assertStringContainsString('1 suggestion(s) generated', $tester->getDisplay());
        self::assertCount(1, $this->suggestionsOf($markup));

        // A blank source text is skipped, not counted as a failure.
        $status = $tester->execute(['--field' => 'description', '--locale' => 'fr_FR', '--source-locale' => 'en_US', '--product' => ['AI_CMD_BLANK_SOURCE']]);
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('No product to process.', $tester->getDisplay());
        self::assertCount(0, $this->suggestionsOf($blankSource));
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
