<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Entity;

use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Provenance marker of a product translation field written from an accepted AI suggestion
 * (EU AI Act art. 50(2) "machine-readable marking"). One row per translation and field.
 */
interface AiProvenanceInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getTranslation(): ?ProductTranslationInterface;

    public function setTranslation(ProductTranslationInterface $translation): void;

    public function getField(): string;

    public function setField(string $field): void;

    public function getGenerator(): string;

    public function getModel(): string;

    public function getGeneratedAt(): \DateTimeImmutable;

    public function getAcceptedAt(): \DateTimeImmutable;

    /** sha256 of the accepted text, used to detect later human edits. */
    public function getContentHash(): string;

    public function getSuggestion(): ?AiSuggestionInterface;

    public function record(AiSuggestionInterface $suggestion, string $acceptedText, \DateTimeImmutable $acceptedAt): void;

    /** True while the field still holds the accepted text, false once a human rewrote it. */
    public function matches(?string $currentText): bool;
}
