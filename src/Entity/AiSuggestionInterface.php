<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A text proposed by the AI generator for one field of one product translation. Nothing is
 * written on the product until a human accepts it.
 *
 * @phpstan-type Warning array{code: string, params?: array<string, string|int>}
 */
interface AiSuggestionInterface extends ResourceInterface
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    public function getId(): ?int;

    public function getProduct(): ?ProductInterface;

    public function setProduct(ProductInterface $product): void;

    public function getLocaleCode(): string;

    public function setLocaleCode(string $localeCode): void;

    public function getField(): string;

    public function setField(string $field): void;

    /** Locale whose text was translated, null when the text was written from the product facts. */
    public function getSourceLocaleCode(): ?string;

    public function setSourceLocaleCode(?string $sourceLocaleCode): void;

    public function isTranslation(): bool;

    /** Value of the field when the suggestion was generated. */
    public function getOriginalText(): ?string;

    public function setOriginalText(?string $originalText): void;

    public function getSuggestedText(): string;

    public function setSuggestedText(string $suggestedText): void;

    public function getStatus(): string;

    public function isPending(): bool;

    public function accept(?AdminUserInterface $decidedBy, \DateTimeImmutable $decidedAt): void;

    public function reject(?AdminUserInterface $decidedBy, \DateTimeImmutable $decidedAt): void;

    /** Generator type: "demo", "platform" or "service". */
    public function getGenerator(): string;

    public function setGenerator(string $generator): void;

    public function getModel(): string;

    public function setModel(string $model): void;

    public function getPromptHash(): string;

    public function setPromptHash(string $promptHash): void;

    public function getInputTokens(): ?int;

    public function getOutputTokens(): ?int;

    public function setTokens(?int $inputTokens, ?int $outputTokens): void;

    /** @return list<Warning> */
    public function getWarnings(): array;

    /** @param list<Warning> $warnings */
    public function setWarnings(array $warnings): void;

    public function getRequestedBy(): ?AdminUserInterface;

    public function setRequestedBy(?AdminUserInterface $requestedBy): void;

    public function getDecidedBy(): ?AdminUserInterface;

    public function getCreatedAt(): \DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    public function getDecidedAt(): ?\DateTimeImmutable;
}
