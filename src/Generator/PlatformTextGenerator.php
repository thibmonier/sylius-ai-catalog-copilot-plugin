<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Adapter to a Symfony AI platform (Mistral, OpenAI, Anthropic, Ollama...) configured with
 * symfony/ai-bundle. Credentials stay in the platform configuration (environment variables).
 */
final class PlatformTextGenerator implements TextGeneratorInterface
{
    public const TYPE = 'platform';

    /**
     * @param non-empty-string $model
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly array $options = [],
    ) {
    }

    public function generate(GenerationRequest $request): GeneratedText
    {
        $messages = new MessageBag(
            Message::forSystem($request->prompt->system),
            Message::ofUser($request->prompt->user),
        );

        try {
            $deferred = $this->platform->invoke($this->model, $messages, $this->options);
            $text = $deferred->asText();
            $tokenUsage = $deferred->getResult()->getMetadata()->get('token_usage');
        } catch (\Throwable $exception) {
            throw new GenerationFailedException(sprintf('The AI platform failed to generate the text: %s', $exception->getMessage()), 0, $exception);
        }

        if ('' === trim($text)) {
            throw new GenerationFailedException('The AI platform returned an empty text.');
        }

        return new GeneratedText(
            $text,
            $this->model,
            $tokenUsage instanceof TokenUsageInterface ? $tokenUsage->getPromptTokens() : null,
            $tokenUsage instanceof TokenUsageInterface ? $tokenUsage->getCompletionTokens() : null,
        );
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
