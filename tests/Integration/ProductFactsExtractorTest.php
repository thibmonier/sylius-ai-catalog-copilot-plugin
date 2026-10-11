<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Integration;

use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional\CopilotTestTrait;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractorInterface;

final class ProductFactsExtractorTest extends WebTestCase
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

    public function testItReadsTheCatalogDataOfTheLocale(): void
    {
        $product = $this->createProduct('AI_FACTS', [], 'Organic linen');
        $taxon = $this->factory('sylius.factory.taxon')->createNew();
        self::assertInstanceOf(TaxonInterface::class, $taxon);
        $taxon->setCode('AI_FACTS_TAXON');
        $taxon->setCurrentLocale('fr_FR');
        $taxon->setName('Chemises');
        $taxon->setSlug('ai-facts-chemises');
        $this->entityManager()->persist($taxon);
        $product->setMainTaxon($taxon);
        $this->entityManager()->flush();

        /** @var ProductFactsExtractorInterface $extractor */
        $extractor = self::getContainer()->get('tmonier_ai_copilot.facts_extractor');
        $facts = $extractor->extract($product, 'fr_FR', 'en_US');

        self::assertSame('fr_FR', $facts->localeCode);
        self::assertSame('AI_FACTS', $facts->code);
        self::assertSame('Chemise en lin AI_FACTS', $facts->name);
        self::assertSame(['Chemises'], $facts->taxons);
        // Attribute names fall back to the default locale.
        self::assertSame([['name' => 'Material', 'value' => 'Organic linen']], $facts->attributes);
        self::assertSame('material: organic linen', $facts->attributesText());
    }
}
