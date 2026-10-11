<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * @phpstan-import-type Warning from AiSuggestionInterface
 */
class AiSuggestion implements AiSuggestionInterface
{
    protected ?int $id = null;

    protected ?ProductInterface $product = null;

    protected string $localeCode = '';

    protected string $field = '';

    protected ?string $sourceLocaleCode = null;

    protected ?string $originalText = null;

    protected string $suggestedText = '';

    protected string $status = self::STATUS_PENDING;

    protected string $generator = '';

    protected string $model = '';

    protected string $promptHash = '';

    protected ?int $inputTokens = null;

    protected ?int $outputTokens = null;

    /** @var list<Warning> */
    protected array $warnings = [];

    protected ?AdminUserInterface $requestedBy = null;

    protected ?AdminUserInterface $decidedBy = null;

    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $decidedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(ProductInterface $product): void
    {
        $this->product = $product;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getSourceLocaleCode(): ?string
    {
        return $this->sourceLocaleCode;
    }

    public function setSourceLocaleCode(?string $sourceLocaleCode): void
    {
        $this->sourceLocaleCode = $sourceLocaleCode;
    }

    public function isTranslation(): bool
    {
        return null !== $this->sourceLocaleCode;
    }

    public function getOriginalText(): ?string
    {
        return $this->originalText;
    }

    public function setOriginalText(?string $originalText): void
    {
        $this->originalText = $originalText;
    }

    public function getSuggestedText(): string
    {
        return $this->suggestedText;
    }

    public function setSuggestedText(string $suggestedText): void
    {
        $this->suggestedText = $suggestedText;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function accept(?AdminUserInterface $decidedBy, \DateTimeImmutable $decidedAt): void
    {
        $this->decide(self::STATUS_ACCEPTED, $decidedBy, $decidedAt);
    }

    public function reject(?AdminUserInterface $decidedBy, \DateTimeImmutable $decidedAt): void
    {
        $this->decide(self::STATUS_REJECTED, $decidedBy, $decidedAt);
    }

    public function getGenerator(): string
    {
        return $this->generator;
    }

    public function setGenerator(string $generator): void
    {
        $this->generator = $generator;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getPromptHash(): string
    {
        return $this->promptHash;
    }

    public function setPromptHash(string $promptHash): void
    {
        $this->promptHash = $promptHash;
    }

    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function setTokens(?int $inputTokens, ?int $outputTokens): void
    {
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function setWarnings(array $warnings): void
    {
        $this->warnings = $warnings;
    }

    public function getRequestedBy(): ?AdminUserInterface
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?AdminUserInterface $requestedBy): void
    {
        $this->requestedBy = $requestedBy;
    }

    public function getDecidedBy(): ?AdminUserInterface
    {
        return $this->decidedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    private function decide(string $status, ?AdminUserInterface $decidedBy, \DateTimeImmutable $decidedAt): void
    {
        if (!$this->isPending()) {
            throw new \LogicException(sprintf('The suggestion is already %s.', $this->status));
        }

        $this->status = $status;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = $decidedAt;
    }
}
