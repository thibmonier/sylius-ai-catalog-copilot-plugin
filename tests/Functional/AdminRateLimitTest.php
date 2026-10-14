<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

/**
 * Rate-limiting on SuggestionController::generateAction.
 *
 * Test-application limits (tests/TestApplication/config/config.yaml):
 *   per_admin: 3   — maximum 3 generations per admin per hour
 *   global:    6   — maximum 6 generations across all admins per hour
 *
 * The per_admin limit fires before the global limit when a single admin is used (3 < 6),
 * so it can be tested without needing a second admin account.
 */
final class AdminRateLimitTest extends WebTestCase
{
    use CopilotTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();

        // The rate-limiter pool lives in the filesystem, outside the DB transaction,
        // so it must be cleared explicitly between tests to prevent cross-test pollution.
        $pool = self::getContainer()->get('cache.tmonier_ai_copilot_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testGenerationIsLimitedPerAdminAndFlashesAnError(): void
    {
        $product = $this->createProduct('RATE_LIMIT_ADMIN');
        $this->loginAdmin();

        // Fetch the CSRF token once — it is not consumed on use and stays valid for the session.
        $this->client->request('GET', $this->productUrl($product));
        $csrfToken = $this->tokenOnPage('form#tmonier-ai-copilot-generate');
        $generateUrl = sprintf('/admin/products/%d/ai-suggestions/generate', $this->idOf($product));

        // The test application allows 3 generations per admin: all three must succeed.
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->client->request('POST', $generateUrl, [
                '_csrf_token' => $csrfToken,
                'target' => 'description|en_US',
            ]);
            self::assertResponseRedirects(
                $this->productUrl($product) . '#tmonier-ai-copilot',
                302,
                sprintf('Attempt %d should succeed and redirect to the product page.', $attempt),
            );
            $this->client->followRedirect();
        }

        $this->entityManager()->clear();
        $suggestions = $this->entityManager()->getRepository(AiSuggestionInterface::class)->findBy(['product' => $product]);
        self::assertCount(3, $suggestions, 'Three suggestions must have been generated.');

        // The 4th request must be denied: per_admin quota exhausted.
        $this->client->request('POST', $generateUrl, [
            '_csrf_token' => $csrfToken,
            'target' => 'description|en_US',
        ]);

        self::assertResponseRedirects($this->productUrl($product) . '#tmonier-ai-copilot');
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists(
            '[data-test-sylius-flash-message-type="error"]',
            'A rate-limit flash error must be shown.',
        );

        // No additional suggestion must have been created after the limit was hit.
        $this->entityManager()->clear();
        $suggestions = $this->entityManager()->getRepository(AiSuggestionInterface::class)->findBy(['product' => $product]);
        self::assertCount(3, $suggestions, 'No extra suggestion must be generated after the limit is hit.');
    }
}
