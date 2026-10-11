<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusAiCatalogCopilotPlugin\Command\GenerateSuggestionsCommand;
use Tmonier\SyliusAiCatalogCopilotPlugin\Controller\Admin\SuggestionController;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractor;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFactsExtractorInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Fixture\DemoSuggestionsFixture;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuard;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\ContentGuardInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\EnvironmentalClaimDetector;
use Tmonier\SyliusAiCatalogCopilotPlugin\Guard\FieldLimits;
use Tmonier\SyliusAiCatalogCopilotPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\PromptBuilderInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\TwigPromptBuilder;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceProvider;
use Tmonier\SyliusAiCatalogCopilotPlugin\Provenance\ProvenanceProviderInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiProvenanceRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Repository\AiSuggestionRepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplier;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionApplierInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Suggestion\SuggestionGeneratorInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Twig\AiCopilotExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(AiSuggestionRepositoryInterface::class, 'tmonier_ai_copilot.repository.ai_suggestion');
    $services->alias(AiProvenanceRepositoryInterface::class, 'tmonier_ai_copilot.repository.ai_provenance');

    // Generation pipeline: facts -> prompt -> generator -> guard -> pending suggestion
    $services->set('tmonier_ai_copilot.facts_extractor', ProductFactsExtractor::class);
    $services->alias(ProductFactsExtractorInterface::class, 'tmonier_ai_copilot.facts_extractor');

    $services
        ->set('tmonier_ai_copilot.field_limits', FieldLimits::class)
        ->args([param('tmonier_ai_copilot.limits')])
    ;

    $services
        ->set('tmonier_ai_copilot.prompt_builder', TwigPromptBuilder::class)
        ->args([
            service('twig'),
            service('tmonier_ai_copilot.field_limits'),
        ])
    ;
    $services->alias(PromptBuilderInterface::class, 'tmonier_ai_copilot.prompt_builder');

    $services->set('tmonier_ai_copilot.generator.demo', DemoTextGenerator::class);

    $services
        ->set('tmonier_ai_copilot.guard.environmental_claims', EnvironmentalClaimDetector::class)
        ->args([
            param('tmonier_ai_copilot.environmental_claims.recognised_labels'),
            param('tmonier_ai_copilot.environmental_claims.ecolabel_attributes'),
            param('tmonier_ai_copilot.environmental_claims.biodegradable_attributes'),
        ])
    ;

    $services
        ->set('tmonier_ai_copilot.guard', ContentGuard::class)
        ->args([
            service('tmonier_ai_copilot.guard.environmental_claims'),
            service('tmonier_ai_copilot.field_limits'),
            param('tmonier_ai_copilot.environmental_claims.mode'),
        ])
    ;
    $services->alias(ContentGuardInterface::class, 'tmonier_ai_copilot.guard');

    $suggestionGeneratorArguments = static fn (string $generator): array => [
        service('tmonier_ai_copilot.facts_extractor'),
        service('tmonier_ai_copilot.prompt_builder'),
        service($generator),
        service('tmonier_ai_copilot.guard'),
        service('tmonier_ai_copilot.factory.ai_suggestion'),
        service('tmonier_ai_copilot.repository.ai_suggestion'),
        service('tmonier_ai_copilot.manager.ai_suggestion'),
        service('clock'),
        service('logger'),
        param('tmonier_ai_copilot.source_locale'),
    ];

    $services
        ->set('tmonier_ai_copilot.suggestion_generator', SuggestionGenerator::class)
        ->args($suggestionGeneratorArguments('tmonier_ai_copilot.generator'))
        ->tag('monolog.logger', ['channel' => 'tmonier_ai_copilot'])
    ;
    $services->alias(SuggestionGeneratorInterface::class, 'tmonier_ai_copilot.suggestion_generator');

    // Same pipeline with the demo generator, for the demo fixture only.
    $services
        ->set('tmonier_ai_copilot.suggestion_generator.demo', SuggestionGenerator::class)
        ->args($suggestionGeneratorArguments('tmonier_ai_copilot.generator.demo'))
    ;

    $services
        ->set('tmonier_ai_copilot.suggestion_applier', SuggestionApplier::class)
        ->args([
            service('tmonier_ai_copilot.repository.ai_provenance'),
            service('tmonier_ai_copilot.factory.ai_provenance'),
            service('tmonier_ai_copilot.manager.ai_suggestion'),
            service('event_dispatcher'),
            service('clock'),
            param('tmonier_ai_copilot.provenance.enabled'),
        ])
    ;
    $services->alias(SuggestionApplierInterface::class, 'tmonier_ai_copilot.suggestion_applier');

    $services
        ->set('tmonier_ai_copilot.provenance_provider', ProvenanceProvider::class)
        ->args([service('tmonier_ai_copilot.repository.ai_provenance')])
        ->tag('kernel.reset', ['method' => 'reset'])
    ;
    $services->alias(ProvenanceProviderInterface::class, 'tmonier_ai_copilot.provenance_provider');

    // Admin
    $services
        ->set('tmonier_ai_copilot.controller.admin.suggestion', SuggestionController::class)
        ->args([
            service('sylius.repository.product'),
            service('tmonier_ai_copilot.repository.ai_suggestion'),
            service('tmonier_ai_copilot.suggestion_generator'),
            service('tmonier_ai_copilot.suggestion_applier'),
        ])
        ->call('setContainer', [service(\Psr\Container\ContainerInterface::class)])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public()
    ;

    $services
        ->set('tmonier_ai_copilot.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;

    $services
        ->set('tmonier_ai_copilot.twig.extension', AiCopilotExtension::class)
        ->args([
            service('tmonier_ai_copilot.provenance_provider'),
            service('tmonier_ai_copilot.repository.ai_suggestion'),
            param('tmonier_ai_copilot.generator_type'),
            param('tmonier_ai_copilot.source_locale'),
        ])
        ->tag('twig.extension')
    ;

    // Console and fixtures
    $services
        ->set('tmonier_ai_copilot.command.generate', GenerateSuggestionsCommand::class)
        ->args([
            service('tmonier_ai_copilot.suggestion_generator'),
            service('tmonier_ai_copilot.manager.ai_suggestion'),
            service('translator'),
            param('sylius.model.product.class'),
            param('tmonier_ai_copilot.model.ai_suggestion.class'),
            param('tmonier_ai_copilot.max_products_per_run'),
        ])
        ->tag('console.command')
    ;

    $services
        ->set('tmonier_ai_copilot.fixture.demo_suggestions', DemoSuggestionsFixture::class)
        ->args([
            service('tmonier_ai_copilot.suggestion_generator.demo'),
            service('tmonier_ai_copilot.suggestion_applier'),
            service('tmonier_ai_copilot.manager.ai_suggestion'),
            param('sylius.model.product.class'),
        ])
        ->tag('sylius_fixtures.fixture')
    ;
};
