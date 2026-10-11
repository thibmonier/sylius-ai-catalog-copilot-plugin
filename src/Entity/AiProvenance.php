<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Entity;

use Sylius\Component\Core\Model\ProductTranslationInterface;

class AiProvenance implements AiProvenanceInterface
{
    protected ?int $id = null;

    protected ?ProductTranslationInterface $translation = null;

    protected string $field = '';

    protected string $generator = '';

    protected string $model = '';

    protected \DateTimeImmutable $generatedAt;

    protected \DateTimeImmutable $acceptedAt;

    protected string $contentHash = '';

    protected ?AiSuggestionInterface $suggestion = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
        $this->acceptedAt = $this->generatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslation(): ?ProductTranslationInterface
    {
        return $this->translation;
    }

    public function setTranslation(ProductTranslationInterface $translation): void
    {
        $this->translation = $translation;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getGenerator(): string
    {
        return $this->generator;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getAcceptedAt(): \DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getSuggestion(): ?AiSuggestionInterface
    {
        return $this->suggestion;
    }

    public function record(AiSuggestionInterface $suggestion, string $acceptedText, \DateTimeImmutable $acceptedAt): void
    {
        $this->suggestion = $suggestion;
        $this->field = $suggestion->getField();
        $this->generator = $suggestion->getGenerator();
        $this->model = $suggestion->getModel();
        $this->generatedAt = $suggestion->getCreatedAt();
        $this->acceptedAt = $acceptedAt;
        $this->contentHash = self::hash($acceptedText);
    }

    public function matches(?string $currentText): bool
    {
        return null !== $currentText && hash_equals($this->contentHash, self::hash($currentText));
    }

    private static function hash(string $text): string
    {
        return hash('sha256', $text);
    }
}
