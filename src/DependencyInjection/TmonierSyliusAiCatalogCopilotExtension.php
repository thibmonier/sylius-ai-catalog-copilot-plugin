<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\SwitchableTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\TextGeneratorInterface;

final class TmonierSyliusAiCatalogCopilotExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{generator: array{type: string, platform: ?string, model: ?string, model_class: ?string, options: array<string, mixed>, service: ?string}, source_locale: ?string, max_products_per_run: int, environmental_claims: array{mode: string, recognised_labels: array<string>, ecolabel_attributes: array<string>, biodegradable_attributes: array<string>}, limits: array<string, array{min?: int, max?: int}>, provenance: array{enabled: bool, shop_notice: bool}, rate_limit: array{enabled: bool, per_admin: int, global: int, interval: string, cache_pool: string}, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_ai_copilot', $config['driver'], $config['resources'], $container);

        // An environment variable placeholder is resolved at runtime.
        $container->setParameter('tmonier_ai_copilot.generator_type', $config['generator']['type']);
        $container->setParameter('tmonier_ai_copilot.source_locale', $config['source_locale'] ?? '%locale%');
        $container->setParameter('tmonier_ai_copilot.max_products_per_run', $config['max_products_per_run']);
        $container->setParameter('tmonier_ai_copilot.environmental_claims.mode', $config['environmental_claims']['mode']);
        $container->setParameter('tmonier_ai_copilot.environmental_claims.recognised_labels', array_values($config['environmental_claims']['recognised_labels']));
        $container->setParameter('tmonier_ai_copilot.environmental_claims.ecolabel_attributes', array_values($config['environmental_claims']['ecolabel_attributes']));
        $container->setParameter('tmonier_ai_copilot.environmental_claims.biodegradable_attributes', array_values($config['environmental_claims']['biodegradable_attributes']));
        $container->setParameter('tmonier_ai_copilot.limits', $config['limits']);
        $container->setParameter('tmonier_ai_copilot.provenance.enabled', $config['provenance']['enabled']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $this->registerGenerator($config['generator'], $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Only the nodes needed here: the other values may still be unresolved env placeholders at
        // this stage, which typed nodes reject.
        /** @var array{provenance: array{enabled: bool, shop_notice: bool}, rate_limit: array{enabled: bool, per_admin: int, global: int, interval: string, cache_pool: string}, resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), array_map(
            static fn (array $config): array => array_intersect_key($config, ['provenance' => true, 'rate_limit' => true, 'resources' => true]),
            $container->getExtensionConfig($this->getAlias()),
        ));
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(sprintf('tmonier_ai_copilot.model.%s.class', $name), $resource['classes']['model']);
        }

        if ($config['rate_limit']['enabled'] && class_exists(RateLimiterFactory::class)) {
            $limiter = static fn (int $limit): array => [
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => $config['rate_limit']['interval'],
                'cache_pool' => $config['rate_limit']['cache_pool'],
                // Without a lock, parallel requests read the same count and all get through.
                'lock_factory' => 'lock.factory',
            ];
            // The default lock store (flock) unless the application configures the lock component.
            $container->prependExtensionConfig('framework', ['lock' => ['enabled' => true, 'resources' => ['default' => ['flock']]]]);
            $container->prependExtensionConfig('framework', ['rate_limiter' => [
                'tmonier_ai_copilot_per_admin' => $limiter($config['rate_limit']['per_admin']),
                'tmonier_ai_copilot_global' => $limiter($config['rate_limit']['global']),
            ]]);
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusAiCatalogCopilotPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusAiCatalogCopilotPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_shop.product.show.content.info.overview.accordion.details' => [
                    'tmonier_ai_copilot_notice' => [
                        'template' => '@TmonierSyliusAiCatalogCopilotPlugin/shop/product/ai_notice.html.twig',
                        'priority' => -100,
                        'enabled' => $config['provenance']['enabled'] && $config['provenance']['shop_notice'],
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusAiCatalogCopilotPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusAiCatalogCopilotPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    /**
     * @param array{type: string, platform: ?string, model: ?string, model_class: ?string, options: array<string, mixed>, service: ?string} $generator
     */
    private function registerGenerator(array $generator, ContainerBuilder $container): void
    {
        $switchable = Configuration::isEnvPlaceholder($generator['type']);
        if (Configuration::GENERATOR_PLATFORM === $generator['type'] || $switchable) {
            $container
                ->register('tmonier_ai_copilot.generator.platform', PlatformTextGenerator::class)
                ->setArguments([
                    new Reference((string) $generator['platform']),
                    (string) $generator['model'],
                    $generator['options'],
                    $generator['model_class'],
                ])
            ;
        }

        if ($switchable) {
            $container
                ->register('tmonier_ai_copilot.generator.switchable', SwitchableTextGenerator::class)
                ->setArguments([
                    $generator['type'],
                    new Reference('tmonier_ai_copilot.generator.demo'),
                    ServiceLocatorTagPass::register($container, [
                        PlatformTextGenerator::TYPE => new Reference('tmonier_ai_copilot.generator.platform'),
                    ]),
                ])
            ;
        }

        $generatorId = match (true) {
            $switchable => 'tmonier_ai_copilot.generator.switchable',
            Configuration::GENERATOR_PLATFORM === $generator['type'] => 'tmonier_ai_copilot.generator.platform',
            Configuration::GENERATOR_SERVICE === $generator['type'] => (string) $generator['service'],
            default => 'tmonier_ai_copilot.generator.demo',
        };

        $container->setAlias('tmonier_ai_copilot.generator', $generatorId);
        $container->setAlias(TextGeneratorInterface::class, 'tmonier_ai_copilot.generator');
    }
}
