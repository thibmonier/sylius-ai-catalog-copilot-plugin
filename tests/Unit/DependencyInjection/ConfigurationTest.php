<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tmonier\SyliusAiCatalogCopilotPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsUseTheDemoGeneratorWithProvenanceAndClaimRemoval(): void
    {
        $config = $this->process([]);

        self::assertSame('demo', $config['generator']['type']);
        self::assertTrue($config['provenance']['enabled']);
        self::assertFalse($config['provenance']['shop_notice']);
        self::assertSame('remove', $config['environmental_claims']['mode']);
        self::assertSame(50, $config['max_products_per_run']);
        self::assertNull($config['source_locale']);
    }

    public function testThePlatformGeneratorNeedsAPlatformAndAModel(): void
    {
        $config = $this->process(['generator' => ['type' => 'platform', 'platform' => 'ai.platform.mistral', 'model' => 'mistral-small-latest', 'options' => ['temperature' => 0.3]]]);
        self::assertSame(['temperature' => 0.3], $config['generator']['options']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['generator' => ['type' => 'platform', 'platform' => 'ai.platform.mistral']]);
    }

    public function testTheServiceGeneratorNeedsAServiceId(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['generator' => ['type' => 'service']]);
    }

    public function testLimitsAreOnlyAllowedForCopilotFields(): void
    {
        self::assertSame(['meta_description' => ['max' => 150]], $this->process(['limits' => ['meta_description' => ['max' => 150]]])['limits']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['limits' => ['name' => ['max' => 10]]]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), [$config]);

        return $processed;
    }
}
