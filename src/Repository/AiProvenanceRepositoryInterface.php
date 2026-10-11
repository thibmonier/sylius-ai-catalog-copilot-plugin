<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Repository;

use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;

/**
 * @extends RepositoryInterface<AiProvenanceInterface>
 */
interface AiProvenanceRepositoryInterface extends RepositoryInterface
{
    public function findOneByTranslationAndField(ProductTranslationInterface $translation, string $field): ?AiProvenanceInterface;

    /** @return array<string, AiProvenanceInterface> provenances of the translation, indexed by field */
    public function findByTranslation(ProductTranslationInterface $translation): array;
}
