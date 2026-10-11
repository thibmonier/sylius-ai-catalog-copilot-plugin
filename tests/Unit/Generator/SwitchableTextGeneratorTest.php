<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\DemoTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformUnavailableException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\SwitchableTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\Prompt;

final class SwitchableTextGeneratorTest extends TestCase
{
    public function testTheDemoModeNeverCreatesThePlatform(): void
    {
        $created = false;
        $locator = new ServiceLocator(['platform' => static function () use (&$created): PlatformTextGenerator {
            $created = true;

            return new PlatformTextGenerator(new InMemoryPlatform('AI text'), 'claude-haiku-5-5');
        }]);

        $generator = new SwitchableTextGenerator('demo', new DemoTextGenerator(), $locator);

        self::assertSame('demo', $generator->getType());
        self::assertSame('demo', $generator->generate($this->request())->model);
        self::assertFalse($created);
        // Any value other than "platform" falls back to the demo generator.
        self::assertSame('demo', (new SwitchableTextGenerator('', new DemoTextGenerator(), $locator))->getType());
    }

    public function testThePlatformModeUsesThePlatformGenerator(): void
    {
        $locator = new ServiceLocator(['platform' => static fn (): PlatformTextGenerator => new PlatformTextGenerator(new InMemoryPlatform('AI text'), 'claude-haiku-5-5')]);

        $generator = new SwitchableTextGenerator('platform', new DemoTextGenerator(), $locator);

        self::assertSame('platform', $generator->getType());
        self::assertSame('AI text', $generator->generate($this->request())->text);
    }

    public function testAMissingApiKeyBecomesAPlatformUnavailableFailure(): void
    {
        $locator = new ServiceLocator(['platform' => static function (): never {
            throw new EnvNotFoundException('Environment variable not found: "ANTHROPIC_API_KEY".');
        }]);

        $this->expectException(PlatformUnavailableException::class);
        $this->expectExceptionMessage(EnvNotFoundException::class);

        (new SwitchableTextGenerator('platform', new DemoTextGenerator(), $locator))->generate($this->request());
    }

    private function request(): GenerationRequest
    {
        return new GenerationRequest('meta_keywords', new ProductFacts('en_US', 'SHIRT', 'Shirt'), new Prompt('s', 'u'));
    }
}
