<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface;

/**
 * The test application enables provenance.shop_notice.
 */
final class ShopProvenanceNoticeTest extends WebTestCase
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

    public function testAnAcceptedAiDescriptionIsMarkedInTheShop(): void
    {
        $product = $this->createProduct('AI_SHOP', ['description' => 'Original description']);
        $this->applier()->accept($this->generate($product, 'description'), null);

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop-en_us');

        self::assertResponseIsSuccessful();
        $notice = $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]');
        self::assertCount(1, $notice);
        self::assertSame('true', $notice->attr('data-ai-generated'));
        self::assertSame('demo', $notice->attr('data-ai-model'));
        self::assertStringContainsString('written with the help of artificial intelligence', $notice->text());
    }

    public function testNoNoticeWithoutAiTextOrAfterAHumanRewrite(): void
    {
        $product = $this->createProduct('AI_SHOP_HUMAN', ['description' => 'Original description']);

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop_human-en_us');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]'));

        $this->applier()->accept($this->generate($product, 'description'), null);
        $this->translation($product, 'en_US')->setDescription('Rewritten by a human');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop_human-en_us');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]'));
    }

    private function applier(): SuggestionApplierInterface
    {
        /** @var SuggestionApplierInterface $applier */
        $applier = self::getContainer()->get('tmonier_ai_copilot.suggestion_applier');

        return $applier;
    }
}
