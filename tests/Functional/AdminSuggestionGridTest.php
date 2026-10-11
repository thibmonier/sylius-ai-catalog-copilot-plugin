<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

final class AdminSuggestionGridTest extends WebTestCase
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

    public function testTheGridListsAndFiltersSuggestions(): void
    {
        $product = $this->createProduct('AI_GRID');
        $pending = $this->generate($product, 'description');
        $rejected = $this->generate($product, 'short_description');
        /** @var \Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface $applier */
        $applier = self::getContainer()->get('tmonier_ai_copilot.suggestion_applier');
        $applier->reject($rejected, null);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/ai-suggestions/', ['criteria' => ['product' => ['type' => 'contains', 'value' => 'AI_GRID'], 'status' => 'pending']]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'AI suggestions');
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-suggested-text="%d"]', $this->idOf($pending))));
        self::assertCount(0, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-suggested-text="%d"]', $this->idOf($rejected))));
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($pending))));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-bulk-accept]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-bulk-reject]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-demo-notice]'));

        $crawler = $this->client->request('GET', '/admin/ai-suggestions/', ['criteria' => ['product' => ['type' => 'contains', 'value' => 'AI_GRID'], 'status' => 'rejected']]);
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-suggested-text="%d"]', $this->idOf($rejected))));
        // Decided suggestions have no decision buttons.
        self::assertCount(0, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($rejected))));
    }

    public function testTheMenuLinksToTheGrid(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/');

        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/ai-suggestions/"]')->count());
    }

    public function testTheSuggestionPageComparesTheTexts(): void
    {
        $product = $this->createProduct('AI_SHOW', ['description' => 'Original description']);
        $suggestion = $this->generate($product, 'description');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', sprintf('/admin/ai-suggestions/%d', $this->idOf($suggestion)));

        self::assertResponseIsSuccessful();
        self::assertSame('Original description', trim($crawler->filter('[data-test-tmonier-ai-copilot-original-text]')->text()));
        self::assertStringContainsString('Material: Linen.', $crawler->filter('[data-test-tmonier-ai-copilot-suggestion-text]')->text());
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-demo-badge]'));
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($suggestion))));

        $this->client->request('POST', sprintf('/admin/ai-suggestions/%d/accept', $this->idOf($suggestion)), [
            '_csrf_token' => $this->tokenOnPage(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($suggestion))),
            '_redirect' => sprintf('/admin/ai-suggestions/%d', $this->idOf($suggestion)),
        ]);
        $crawler = $this->client->followRedirect();

        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-status="accepted"]'));
        self::assertCount(0, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($suggestion))));
    }

    public function testBulkAcceptSkipsStaleAndDecidedSuggestions(): void
    {
        $first = $this->createProduct('AI_BULK_1');
        $second = $this->createProduct('AI_BULK_2', ['description' => 'Original']);
        $accepted = $this->generate($first, 'description');
        $acceptedToo = $this->generate($first, 'meta_keywords');
        $stale = $this->generate($second, 'description');
        $this->translation($second, 'en_US')->setDescription('Rewritten by a human');
        $this->entityManager()->flush();
        $this->loginAdmin();
        $this->client->request('GET', '/admin/ai-suggestions/', ['criteria' => ['product' => ['type' => 'contains', 'value' => 'AI_BULK']]]);
        $token = $this->tokenOnPage('[data-test-tmonier-ai-copilot-bulk-accept]');

        $this->client->request('POST', '/admin/ai-suggestions/bulk/accept', [
            '_csrf_token' => $token,
            'ids' => [$this->idOf($accepted), $this->idOf($acceptedToo), $this->idOf($stale), 999999999],
            '_redirect' => '/admin/ai-suggestions/',
        ]);

        self::assertResponseRedirects('/admin/ai-suggestions/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="success"]', '2 suggestion(s) accepted.');
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="warning"]', '2 suggestion(s) skipped');
        self::assertSame(AiSuggestionInterface::STATUS_ACCEPTED, $this->reload($accepted)->getStatus());
        self::assertSame(AiSuggestionInterface::STATUS_ACCEPTED, $this->reload($acceptedToo)->getStatus());
        self::assertTrue($this->reload($stale)->isPending());
        self::assertNotNull($this->translation($this->reload($first), 'en_US')->getMetaKeywords());
    }

    public function testBulkReject(): void
    {
        $product = $this->createProduct('AI_BULK_REJECT');
        $suggestions = [$this->generate($product, 'description'), $this->generate($product, 'short_description')];
        $this->loginAdmin();
        $this->client->request('GET', '/admin/ai-suggestions/');

        $this->client->request('POST', '/admin/ai-suggestions/bulk/reject', [
            '_csrf_token' => $this->tokenOnPage('[data-test-tmonier-ai-copilot-bulk-reject]'),
            'ids' => array_map(fn (AiSuggestionInterface $suggestion): int => $this->idOf($suggestion), $suggestions),
        ]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="success"]', '2 suggestion(s) rejected.');
        foreach ($suggestions as $suggestion) {
            self::assertSame(AiSuggestionInterface::STATUS_REJECTED, $this->reload($suggestion)->getStatus());
        }
    }

    public function testBulkWithoutSelection(): void
    {
        $this->loginAdmin();
        $this->client->request('GET', '/admin/ai-suggestions/');

        $this->client->request('POST', '/admin/ai-suggestions/bulk/accept', ['_csrf_token' => $this->tokenOnPage('[data-test-tmonier-ai-copilot-bulk-accept]')]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="error"]', 'Select at least one suggestion.');
    }
}
