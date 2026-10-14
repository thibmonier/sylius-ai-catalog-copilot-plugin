<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Generator;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Adapter to a Symfony AI platform (Mistral, OpenAI, Anthropic, Ollama...) configured with
 * symfony/ai-bundle. Credentials stay in the platform configuration (environment variables).
 */
final readonly class PlatformTextGenerator implements TextGeneratorInterface
{
    public const TYPE = 'platform';

    /**
     * @param non-empty-string $model
     * @param array<string, mixed> $options
     * @param class-string<Model>|null $modelClass when set, the model is built with this class instead of
     *                                              being looked up in the platform model catalog
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        private array $options = [],
        private ?string $modelClass = null,
    ) {
    }

    public function generate(GenerationRequest $request): GeneratedText
    {
        $messages = new MessageBag(
            Message::forSystem($request->prompt->system),
            Message::ofUser($request->prompt->user),
        );

        try {
            $deferred = $this->platform->invoke($this->model(), $messages, $this->options);
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

    /** @return non-empty-string|Model */
    private function model(): string|Model
    {
        if (null === $this->modelClass) {
            return $this->model;
        }
        if (!is_a($this->modelClass, Model::class, true)) {
            throw new GenerationFailedException(sprintf('The model class "%s" must extend "%s".', $this->modelClass, Model::class));
        }

        return new ($this->modelClass)($this->model, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
