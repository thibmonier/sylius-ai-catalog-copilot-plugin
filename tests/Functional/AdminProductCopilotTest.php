<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional;

use Sylius\Component\Core\Model\ProductTranslationInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Event\AiSuggestionAccepted;

final class AdminProductCopilotTest extends WebTestCase
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

    public function testTheProductPageShowsTheCopilotToolbarPerLocaleAndTheSuggestionsCard(): void
    {
        $product = $this->createProduct('AI_TOOLBAR');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', $this->productUrl($product));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-toolbar="en_US"]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-toolbar="fr_FR"]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-generate="description|en_US"]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-generate="meta_keywords|fr_FR"]'));
        // "Translate from" is offered for the other locales only.
        self::assertCount(0, $crawler->filter('[data-test-tmonier-ai-copilot-translate-menu="en_US"]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-translate="description|fr_FR"]'));
        self::assertSame('description|fr_FR|en_US', $crawler->filter('[data-test-tmonier-ai-copilot-translate="description|fr_FR"]')->attr('value'));
        // The buttons target a form outside the product form.
        self::assertSame('tmonier-ai-copilot-generate', $crawler->filter('[data-test-tmonier-ai-copilot-generate="description|en_US"]')->attr('form'));
        self::assertCount(1, $crawler->filter('form#tmonier-ai-copilot-generate'));
        self::assertCount(0, $crawler->filter('form form#tmonier-ai-copilot-generate'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-no-pending]'));
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-demo-notice]'));
    }

    public function testGeneratingCreatesAPendingSuggestionWithoutChangingTheProduct(): void
    {
        $product = $this->createProduct('AI_GENERATE', ['description' => 'Original description']);
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product)), [
            '_csrf_token' => $this->tokenOnPage('form#tmonier-ai-copilot-generate'),
            'target' => 'description|en_US',
        ]);

        self::assertResponseRedirects($this->productUrl($product) . '#tmonier-ai-copilot');
        $suggestions = $this->entityManager()->getRepository(AiSuggestionInterface::class)->findBy(['product' => $product]);
        self::assertCount(1, $suggestions);
        $suggestion = $suggestions[0];
        self::assertTrue($suggestion->isPending());
        self::assertSame('demo', $suggestion->getGenerator());
        self::assertSame('Original description', $suggestion->getOriginalText());
        self::assertStringContainsString('Material: Linen.', $suggestion->getSuggestedText());
        self::assertSame('sylius', $suggestion->getRequestedBy()?->getUsername());
        $product = $this->reload($product);
        self::assertSame('Original description', $this->translation($product, 'en_US')->getDescription());

        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter(sprintf('[data-test-tmonier-ai-copilot-pending="%d"]', $this->idOf($suggestion))));
        self::assertSelectorExists('[data-test-sylius-flash-message-type="success"]');
    }

    public function testTranslatingFromTheSourceLocale(): void
    {
        $product = $this->createProduct('AI_TRANSLATE', ['meta_description' => 'A light linen shirt for warm summer days, cut in Europe.']);
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product)), [
            '_csrf_token' => $this->tokenOnPage('form#tmonier-ai-copilot-generate'),
            'target' => 'meta_description|fr_FR|en_US',
        ]);

        self::assertResponseRedirects();
        $suggestion = $this->entityManager()->getRepository(AiSuggestionInterface::class)->findOneBy(['product' => $product]);
        self::assertInstanceOf(AiSuggestionInterface::class, $suggestion);
        self::assertSame('fr_FR', $suggestion->getLocaleCode());
        self::assertSame('en_US', $suggestion->getSourceLocaleCode());
        self::assertSame('[fr_FR] A light linen shirt for warm summer days, cut in Europe.', $suggestion->getSuggestedText());
    }

    public function testAGenerationErrorIsShownAsAFlash(): void
    {
        $product = $this->createProduct('AI_EMPTY_SOURCE');
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product)), [
            '_csrf_token' => $this->tokenOnPage('form#tmonier-ai-copilot-generate'),
            'target' => 'description|fr_FR|en_US',
        ]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="error"]', 'Nothing to translate: the field is empty in en_US.');
        self::assertCount(0, $this->entityManager()->getRepository(AiSuggestionInterface::class)->findBy(['product' => $product]));
    }

    public function testAcceptingWritesTheProductRecordsTheProvenanceAndDispatchesTheEvent(): void
    {
        $product = $this->createProduct('AI_ACCEPT', ['description' => 'Original description']);
        $suggestion = $this->generate($product, 'description');
        $events = [];
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(AiSuggestionAccepted::class, static function (AiSuggestionAccepted $event) use (&$events): void {
            $events[] = $event;
        });
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/ai-suggestions/%d/accept', $this->idOf($suggestion)), [
            '_csrf_token' => $this->tokenOnPage(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($suggestion))),
            '_redirect' => $this->productUrl($product),
        ]);

        self::assertResponseRedirects($this->productUrl($product));
        $suggestion = $this->reload($suggestion);
        $product = $this->reload($product);
        self::assertSame(AiSuggestionInterface::STATUS_ACCEPTED, $suggestion->getStatus());
        self::assertSame('sylius', $suggestion->getDecidedBy()?->getUsername());
        $translation = $product->getTranslations()->get('en_US');
        self::assertInstanceOf(ProductTranslationInterface::class, $translation);
        self::assertSame($suggestion->getSuggestedText(), $translation->getDescription());
        $provenance = $this->entityManager()->getRepository(AiProvenanceInterface::class)->findOneBy(['translation' => $translation, 'field' => 'description']);
        self::assertInstanceOf(AiProvenanceInterface::class, $provenance);
        self::assertSame('demo', $provenance->getModel());
        self::assertCount(1, $events);
        self::assertSame('Original description', $events[0]->previousText);

        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-ai-copilot-provenance="description|en_US"]'));
        self::assertStringContainsString('AI-generated', $crawler->filter('[data-test-tmonier-ai-copilot-provenance="description|en_US"]')->text());
    }

    public function testSavingTheProductFormKeepsTheAiMarkingOfAnUntouchedMultiLineText(): void
    {
        // No test attribute: the attribute form of the admin would not round-trip it.
        $product = $this->createProduct('AI_FORM_SAVE', ['description' => 'Original description'], null);
        $suggestion = $this->generate($product, 'description');
        $suggestion->setSuggestedText("A linen shirt.\n\nMachine washable.");
        $suggestion->setGenerator('platform');
        /** @var \Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface $applier */
        $applier = self::getContainer()->get('tmonier_ai_copilot.suggestion_applier');
        $applier->accept($suggestion, null);
        $this->loginAdmin();

        // Browsers submit textarea line breaks as CRLF: post the product form as a browser would.
        $crawler = $this->client->request('GET', $this->productUrl($product));
        $form = $crawler->filter('form[name="sylius_admin_product"]')->form();
        $field = 'sylius_admin_product[translations][en_US][description]';
        self::assertTrue($form->has($field));
        $form[$field] = str_replace("\n", "\r\n", $suggestion->getSuggestedText()) . "\r\n";
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('AI-generated', $crawler->filter('[data-test-tmonier-ai-copilot-provenance="description|en_US"]')->text());

        // Stored raw with CRLF (imports, API): still the accepted text.
        $product = $this->reload($product);
        $this->translation($product, 'en_US')->setDescription(str_replace("\n", "\r\n", $suggestion->getSuggestedText()));
        $this->entityManager()->flush();
        $crawler = $this->client->request('GET', $this->productUrl($product));
        self::assertStringContainsString('AI-generated', $crawler->filter('[data-test-tmonier-ai-copilot-provenance="description|en_US"]')->text());
    }

    public function testAStaleSuggestionIsRefused(): void
    {
        $product = $this->createProduct('AI_STALE', ['description' => 'Original description']);
        $suggestion = $this->generate($product, 'description');
        $this->translation($product, 'en_US')->setDescription('Rewritten by a human');
        $this->entityManager()->flush();
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/ai-suggestions/%d/accept', $this->idOf($suggestion)), [
            '_csrf_token' => $this->tokenOnPage(sprintf('[data-test-tmonier-ai-copilot-accept="%d"]', $this->idOf($suggestion))),
            '_redirect' => $this->productUrl($product),
        ]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-sylius-flash-message-type="error"]', 'The field changed since the suggestion was generated');
        $suggestion = $this->reload($suggestion);
        $product = $this->reload($product);
        self::assertTrue($suggestion->isPending());
        self::assertSame('Rewritten by a human', $this->translation($product, 'en_US')->getDescription());
    }

    public function testRejecting(): void
    {
        $product = $this->createProduct('AI_REJECT', ['description' => 'Original description']);
        $suggestion = $this->generate($product, 'description');
        $this->loginAdmin();
        $this->client->request('GET', $this->productUrl($product));

        $this->client->request('POST', sprintf('/admin/ai-suggestions/%d/reject', $this->idOf($suggestion)), [
            '_csrf_token' => $this->tokenOnPage(sprintf('[data-test-tmonier-ai-copilot-reject="%d"]', $this->idOf($suggestion))),
            '_redirect' => 'https://evil.example/phishing',
        ]);

        // External redirects are ignored.
        self::assertResponseRedirects('/admin/ai-suggestions/');
        $suggestion = $this->reload($suggestion);
        $product = $this->reload($product);
        self::assertSame(AiSuggestionInterface::STATUS_REJECTED, $suggestion->getStatus());
        self::assertSame('Original description', $this->translation($product, 'en_US')->getDescription());
    }

    public function testActionsNeedAValidCsrfToken(): void
    {
        $product = $this->createProduct('AI_CSRF');
        $suggestion = $this->generate($product, 'description');
        $this->loginAdmin();

        $this->client->request('POST', sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product)), ['_csrf_token' => 'invalid', 'target' => 'description|en_US']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', sprintf('/admin/ai-suggestions/%d/accept', $this->idOf($suggestion)), ['_csrf_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/ai-suggestions/bulk/accept', ['_csrf_token' => 'invalid', 'ids' => [$this->idOf($suggestion)]]);
        self::assertResponseStatusCodeSame(403);

        $suggestion = $this->reload($suggestion);
        self::assertTrue($suggestion->isPending());
    }

    public function testAnonymousUsersAreRedirectedToTheLogin(): void
    {
        $product = $this->createProduct('AI_ANONYMOUS');

        $this->client->request('POST', sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product)), ['target' => 'description|en_US']);

        self::assertResponseRedirects('http://localhost/admin/login');
    }
}
