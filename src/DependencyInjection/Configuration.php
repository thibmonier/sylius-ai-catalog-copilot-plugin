<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenance;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestion;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuard;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepository;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepository;

final class Configuration implements ConfigurationInterface
{
    public const GENERATOR_DEMO = 'demo';

    public const GENERATOR_PLATFORM = 'platform';

    public const GENERATOR_SERVICE = 'service';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_ai_catalog_copilot');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('generator')
                    ->info('Text generator. "demo" assembles texts from the product attributes without any AI call; "platform" uses a Symfony AI platform configured with symfony/ai-bundle; "service" uses your own TextGeneratorInterface service.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')->values([self::GENERATOR_DEMO, self::GENERATOR_PLATFORM, self::GENERATOR_SERVICE])->defaultValue(self::GENERATOR_DEMO)->end()
                        ->scalarNode('platform')->info('Service id of the Symfony AI platform, e.g. "ai.platform.mistral".')->defaultNull()->end()
                        ->scalarNode('model')->info('Model name, e.g. "mistral-small-latest".')->defaultNull()->end()
                        ->arrayNode('options')
                            ->info('Options passed to the platform on each call, e.g. {temperature: 0.3}.')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->scalarNode('service')->info('Service id implementing TextGeneratorInterface (type "service").')->defaultNull()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $generator): bool => self::GENERATOR_PLATFORM === $generator['type'] && (empty($generator['platform']) || empty($generator['model'])))
                        ->thenInvalid('The "platform" generator needs both "platform" (service id) and "model".')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $generator): bool => self::GENERATOR_SERVICE === $generator['type'] && empty($generator['service']))
                        ->thenInvalid('The "service" generator needs the "service" id.')
                    ->end()
                ->end()
                ->scalarNode('source_locale')
                    ->info('Locale the "Translate from" buttons translate from. Defaults to the %locale% parameter.')
                    ->defaultNull()
                ->end()
                ->integerNode('max_products_per_run')
                    ->info('Maximum number of products processed by one tmonier:ai-copilot:generate run.')
                    ->min(1)
                    ->defaultValue(50)
                ->end()
                ->arrayNode('environmental_claims')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')
                            ->info('Unsubstantiated generic environmental claims (Directive (EU) 2024/825): "remove" the sentence, or only "flag" it.')
                            ->values([ContentGuard::CLAIMS_REMOVE, ContentGuard::CLAIMS_FLAG])
                            ->defaultValue(ContentGuard::CLAIMS_REMOVE)
                        ->end()
                        ->arrayNode('recognised_labels')
                            ->info('Extra ecolabel names which, found in a product attribute, substantiate a generic claim.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('limits')
                    ->info('Length limits in characters, per field.')
                    ->useAttributeAsKey('field')
                    ->validate()
                        ->ifTrue(static fn (array $limits): bool => [] !== array_diff(array_keys($limits), ProductField::ALL))
                        ->thenInvalid(sprintf('Limits can only be set for: %s.', implode(', ', ProductField::ALL)))
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('min')->min(0)->end()
                            ->integerNode('max')->min(1)->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('provenance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Record which fields were written from an AI suggestion (model, date) - EU AI Act art. 50(2) marking.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('shop_notice')
                            ->info('Show "written with AI" with data-ai-* attributes under the product description in the shop.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
            ->end()
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('ai_suggestion')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(AiSuggestion::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(AiSuggestionInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                        ->scalarNode('repository')->defaultValue(AiSuggestionRepository::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('ai_provenance')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(AiProvenance::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(AiProvenanceInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                        ->scalarNode('repository')->defaultValue(AiProvenanceRepository::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
