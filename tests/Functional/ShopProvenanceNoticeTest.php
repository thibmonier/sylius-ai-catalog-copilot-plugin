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
        $suggestion = $this->generate($product, 'description');
        // As if a configured Symfony AI platform had written it (the test app uses the demo generator).
        $suggestion->setGenerator('platform');
        $suggestion->setModel('mistral-small-latest');
        $this->applier()->accept($suggestion, null);

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop-en_us');

        self::assertResponseIsSuccessful();
        $notice = $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]');
        self::assertCount(1, $notice);
        self::assertSame('true', $notice->attr('data-ai-generated'));
        self::assertSame('mistral-small-latest', $notice->attr('data-ai-model'));
        self::assertSame('platform', $notice->attr('data-ai-generator'));
        self::assertStringContainsString('written with the help of artificial intelligence', $notice->text());
    }

    public function testNoNoticeWithoutAiTextOrAfterAHumanRewrite(): void
    {
        $product = $this->createProduct('AI_SHOP_HUMAN', ['description' => 'Original description']);

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop_human-en_us');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]'));

        $suggestion = $this->generate($product, 'description');
        $suggestion->setGenerator('platform');
        $this->applier()->accept($suggestion, null);
        self::assertCount(1, $this->client->request('GET', '/en_US/products/ai_shop_human-en_us')->filter('[data-test-tmonier-ai-copilot-shop-notice]'));
        $product = $this->reload($product);
        $this->translation($product, 'en_US')->setDescription('Rewritten by a human');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop_human-en_us');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]'));
    }

    public function testDemoGeneratorTextsAreNeverAdvertisedAsAi(): void
    {
        $product = $this->createProduct('AI_SHOP_DEMO', ['description' => 'Original description']);
        $suggestion = $this->generate($product, 'description');
        self::assertSame('demo', $suggestion->getGenerator());
        $this->applier()->accept($suggestion, null);

        $crawler = $this->client->request('GET', '/en_US/products/ai_shop_demo-en_us');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-shop-notice]'));
        self::assertStringNotContainsString('artificial intelligence', $crawler->filter('[data-test-product-details]')->text());
    }

    private function applier(): SuggestionApplierInterface
    {
        /** @var SuggestionApplierInterface $applier */
        $applier = self::getContainer()->get('tmonier_ai_copilot.suggestion_applier');

        return $applier;
    }
}
