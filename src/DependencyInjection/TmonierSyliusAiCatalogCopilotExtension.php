<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\TextGeneratorInterface;

final class TmonierSyliusAiCatalogCopilotExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{generator: array{type: string, platform: ?string, model: ?string, options: array<string, mixed>, service: ?string}, source_locale: ?string, max_products_per_run: int, environmental_claims: array{mode: string, recognised_labels: array<string>}, limits: array<string, array{min?: int, max?: int}>, provenance: array{enabled: bool, shop_notice: bool}, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_ai_copilot', $config['driver'], $config['resources'], $container);

        $container->setParameter('tmonier_ai_copilot.generator_type', $config['generator']['type']);
        $container->setParameter('tmonier_ai_copilot.source_locale', $config['source_locale'] ?? '%locale%');
        $container->setParameter('tmonier_ai_copilot.max_products_per_run', $config['max_products_per_run']);
        $container->setParameter('tmonier_ai_copilot.environmental_claims.mode', $config['environmental_claims']['mode']);
        $container->setParameter('tmonier_ai_copilot.environmental_claims.recognised_labels', array_values($config['environmental_claims']['recognised_labels']));
        $container->setParameter('tmonier_ai_copilot.limits', $config['limits']);
        $container->setParameter('tmonier_ai_copilot.provenance.enabled', $config['provenance']['enabled']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $this->registerGenerator($config['generator'], $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        /** @var array{provenance: array{enabled: bool, shop_notice: bool}, resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(sprintf('tmonier_ai_copilot.model.%s.class', $name), $resource['classes']['model']);
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
     * @param array{type: string, platform: ?string, model: ?string, options: array<string, mixed>, service: ?string} $generator
     */
    private function registerGenerator(array $generator, ContainerBuilder $container): void
    {
        $generatorId = match ($generator['type']) {
            Configuration::GENERATOR_PLATFORM => 'tmonier_ai_copilot.generator.platform',
            Configuration::GENERATOR_SERVICE => (string) $generator['service'],
            default => 'tmonier_ai_copilot.generator.demo',
        };

        if (Configuration::GENERATOR_PLATFORM === $generator['type']) {
            $container
                ->register('tmonier_ai_copilot.generator.platform', PlatformTextGenerator::class)
                ->setArguments([
                    new Reference((string) $generator['platform']),
                    (string) $generator['model'],
                    $generator['options'],
                ])
            ;
        }

        $container->setAlias('tmonier_ai_copilot.generator', $generatorId);
        $container->setAlias(TextGeneratorInterface::class, 'tmonier_ai_copilot.generator');
    }
}
