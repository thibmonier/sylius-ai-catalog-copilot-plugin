<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAiCatalogCopilotPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Tmonier\SyliusAiCatalogCopilotPlugin\Facts\ProductFacts;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationFailedException;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\GenerationRequest;
use Tmonier\SyliusAiCatalogCopilotPlugin\Generator\PlatformTextGenerator;
use Tmonier\SyliusAiCatalogCopilotPlugin\Model\ProductField;
use Tmonier\SyliusAiCatalogCopilotPlugin\Prompt\Prompt;

/**
 * Adapter test against the Symfony AI mock platform (MockPlatformFactory): no network, no real AI service.
 */
final class PlatformTextGeneratorTest extends TestCase
{
    public function testItSendsTheSystemAndUserMessagesToTheConfiguredModel(): void
    {
        $calls = [];
        $platform = MockPlatformFactory::createPlatform(static function (Model $model, array|string|object $input, array $options) use (&$calls): string {
            $calls[] = [$model->getName(), $input, $options];

            return '  A soft cotton shirt.  ';
        });

        $generated = (new PlatformTextGenerator($platform, 'mistral-small-latest', ['temperature' => 0.2]))->generate($this->request());

        self::assertSame('  A soft cotton shirt.  ', $generated->text);
        self::assertSame('mistral-small-latest', $generated->model);
        self::assertNull($generated->inputTokens);
        self::assertCount(1, $calls);
        [$model, $input, $options] = $calls[0];
        self::assertSame('mistral-small-latest', $model);
        self::assertSame(['temperature' => 0.2], $options);
        // The mock platform receives the payload already normalized by the Symfony AI contract.
        self::assertIsArray($input);
        self::assertSame(
            [
                ['role' => 'system', 'content' => 'You write product content.'],
                ['role' => 'user', 'content' => 'Describe the shirt.'],
            ],
            $input['messages'],
        );
        self::assertSame('platform', (new PlatformTextGenerator($platform, 'm'))->getType());
    }

    public function testItReadsTheTokenUsage(): void
    {
        $platform = MockPlatformFactory::createPlatform(static function (): TextResult {
            $result = new TextResult('A soft cotton shirt.');
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 120, completionTokens: 30));

            return $result;
        });

        $generated = (new PlatformTextGenerator($platform, 'gpt-5-mini'))->generate($this->request());

        self::assertSame(120, $generated->inputTokens);
        self::assertSame(30, $generated->outputTokens);
    }

    public function testPlatformErrorsBecomeGenerationFailures(): void
    {
        $platform = MockPlatformFactory::createPlatform(static function (): string {
            throw new \RuntimeException('HTTP 401');
        });

        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('HTTP 401');

        (new PlatformTextGenerator($platform, 'claude-sonnet-5-5'))->generate($this->request());
    }

    public function testAModelClassLetsAModelMissingFromTheCatalogBeUsed(): void
    {
        $received = null;
        $platform = MockPlatformFactory::createPlatform(static function (Model $model) use (&$received): string {
            $received = $model;

            return 'A text.';
        });

        $generated = (new PlatformTextGenerator($platform, 'claude-haiku-5-5', [], Claude::class))->generate($this->request());

        self::assertInstanceOf(Claude::class, $received);
        self::assertSame('claude-haiku-5-5', $received->getName());
        self::assertSame('claude-haiku-5-5', $generated->model);
    }

    public function testAnEmptyAnswerIsAFailure(): void
    {
        $this->expectException(GenerationFailedException::class);

        (new PlatformTextGenerator(MockPlatformFactory::createPlatform('   '), 'llama3'))->generate($this->request());
    }

    private function request(): GenerationRequest
    {
        return new GenerationRequest(
            ProductField::DESCRIPTION,
            new ProductFacts('en_US', 'SHIRT', 'Shirt'),
            new Prompt('You write product content.', 'Describe the shirt.'),
        );
    }
}
