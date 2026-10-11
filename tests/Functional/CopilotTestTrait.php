<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGeneratorInterface;

/**
 * Every test runs in a transaction rolled back afterwards (kernel not rebooted) and creates
 * the products it needs: the Sylius fixtures are random.
 */
trait CopilotTestTrait
{
    private KernelBrowser $client;

    private function setUpClient(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->connection()->beginTransaction();
    }

    private function tearDownClient(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function loginAdmin(string $username = 'sylius'): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }

    /**
     * An enabled product of the shop channel, translated in en_US and fr_FR, with a "Material"
     * text attribute.
     *
     * @param array<string, string|null> $enUs translation fields of en_US (description...)
     */
    private function createProduct(string $code, array $enUs = [], string $material = 'Linen'): ProductInterface
    {
        $container = self::getContainer();
        $productFactory = $container->get('sylius.factory.product');
        self::assertInstanceOf(ProductFactoryInterface::class, $productFactory);
        $product = $productFactory->createWithVariant();
        self::assertInstanceOf(ProductInterface::class, $product);
        $product->setCode($code);
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setEnabled(true);

        foreach (['en_US' => 'Linen shirt ' . $code, 'fr_FR' => 'Chemise en lin ' . $code] as $locale => $name) {
            /** @var ProductTranslationInterface $translation */
            $translation = $this->factory('sylius.factory.product_translation')->createNew();
            $translation->setLocale($locale);
            $translation->setName($name);
            $translation->setSlug(strtolower($code) . '-' . strtolower($locale));
            if ('en_US' === $locale) {
                $translation->setDescription($enUs['description'] ?? null);
                $translation->setMetaDescription($enUs['meta_description'] ?? null);
            }
            $product->addTranslation($translation);
        }

        $channel = $this->channel();
        $product->addChannel($channel);
        $variant = $product->getVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $variant->setCode($code . '_VARIANT');
        $variant->setCurrentLocale('en_US');
        $variant->setName($code);
        /** @var ChannelPricingInterface $pricing */
        $pricing = $this->factory('sylius.factory.channel_pricing')->createNew();
        $pricing->setChannelCode((string) $channel->getCode());
        $pricing->setPrice(2500);
        $variant->addChannelPricing($pricing);

        $attributeFactory = $container->get('sylius.factory.product_attribute');
        self::assertInstanceOf(AttributeFactoryInterface::class, $attributeFactory);
        $attribute = $attributeFactory->createTyped('text');
        self::assertInstanceOf(ProductAttributeInterface::class, $attribute);
        $attribute->setCode('material_' . strtolower($code));
        $attribute->setCurrentLocale('en_US');
        $attribute->setFallbackLocale('en_US');
        $attribute->setName('Material');
        $attribute->setTranslatable(false);
        /** @var ProductAttributeValueInterface $value */
        $value = $this->factory('sylius.factory.product_attribute_value')->createNew();
        $value->setAttribute($attribute);
        $value->setValue($material);
        $product->addAttribute($value);

        $this->entityManager()->persist($attribute);
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $product;
    }

    private function translation(ProductInterface $product, string $locale): ProductTranslationInterface
    {
        $translation = $product->getTranslations()->get($locale);
        self::assertInstanceOf(ProductTranslationInterface::class, $translation);

        return $translation;
    }

    /** @return FactoryInterface<object> */
    private function factory(string $id): FactoryInterface
    {
        $factory = self::getContainer()->get($id);
        self::assertInstanceOf(FactoryInterface::class, $factory);

        return $factory;
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy(['code' => 'FASHION_WEB']);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private function generate(ProductInterface $product, string $field, string $locale = 'en_US', ?string $sourceLocale = null): AiSuggestionInterface
    {
        /** @var SuggestionGeneratorInterface $generator */
        $generator = self::getContainer()->get('tmonier_ai_copilot.suggestion_generator');

        return $generator->generate($product, $field, $locale, $sourceLocale);
    }

    /** CSRF token of the form containing the first element matching $selector on the current page. */
    private function tokenOnPage(string $selector): string
    {
        $element = $this->client->getCrawler()->filter($selector);
        self::assertGreaterThan(0, $element->count(), sprintf('Nothing matches "%s".', $selector));
        $form = $element->first()->closest('form');
        self::assertNotNull($form);

        return (string) $form->filter('input[name="_csrf_token"]')->attr('value');
    }

    private function productUrl(ProductInterface $product): string
    {
        return sprintf('/admin/products/%d/edit', $this->idOf($product));
    }

    /**
     * The entity as stored now (requests reset the entity manager).
     *
     * @template T of ProductInterface|AiSuggestionInterface
     *
     * @param T $resource
     *
     * @return T
     */
    private function reload(ProductInterface|AiSuggestionInterface $resource): ProductInterface|AiSuggestionInterface
    {
        $this->entityManager()->clear();
        $fresh = $this->entityManager()->find($resource::class, $this->idOf($resource));
        self::assertNotNull($fresh);

        return $fresh;
    }

    private function idOf(ProductInterface|AiSuggestionInterface $resource): int
    {
        $id = $resource->getId();
        self::assertIsInt($id);

        return $id;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
