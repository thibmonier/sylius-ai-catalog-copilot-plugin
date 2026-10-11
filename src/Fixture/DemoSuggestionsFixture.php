<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGeneratorInterface;

/**
 * Demo data: pending, accepted and rejected suggestions on the first products. It always uses
 * the deterministic demo generator, never the configured AI platform.
 */
final class DemoSuggestionsFixture extends AbstractFixture
{
    /**
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private readonly SuggestionGeneratorInterface $demoSuggestionGenerator,
        private readonly SuggestionApplierInterface $suggestionApplier,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $productClass,
    ) {
    }

    public function getName(): string
    {
        return 'tmonier_ai_copilot_demo_suggestions';
    }

    /** @param array{products: int, locale: string, translation_locale: string|null} $options */
    public function load(array $options): void
    {
        $locale = $options['locale'];
        $translationLocale = $options['translation_locale'];

        foreach ($this->findProducts($locale, $options['products']) as $index => $product) {
            try {
                // Pending: the description of every product.
                $this->demoSuggestionGenerator->generate($product, ProductField::DESCRIPTION, $locale);

                // Accepted: the meta description, so the provenance marker shows on the product.
                $metaDescription = $this->demoSuggestionGenerator->generate($product, ProductField::META_DESCRIPTION, $locale);
                $this->suggestionApplier->accept($metaDescription, null);

                // Rejected: the short description of every other product.
                if (0 === $index % 2) {
                    $shortDescription = $this->demoSuggestionGenerator->generate($product, ProductField::SHORT_DESCRIPTION, $locale);
                    $this->suggestionApplier->reject($shortDescription, null);
                }

                // Pending translation of the meta description.
                if (null !== $translationLocale && null !== $product->getTranslations()->get($translationLocale)) {
                    $this->demoSuggestionGenerator->generate($product, ProductField::META_DESCRIPTION, $translationLocale, $locale);
                }
            } catch (SuggestionException) {
                continue;
            }
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->integerNode('products')->min(1)->defaultValue(6)->end()
                ->scalarNode('locale')->cannotBeEmpty()->defaultValue('en_US')->end()
                ->scalarNode('translation_locale')->defaultValue('fr_FR')->end()
            ->end()
        ;
    }

    /** @return list<ProductInterface> */
    private function findProducts(string $locale, int $limit): array
    {
        /** @var list<ProductInterface> $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('product')
            ->from($this->productClass, 'product')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->setParameter('locale', $locale)
            ->orderBy('product.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $products;
    }
}
