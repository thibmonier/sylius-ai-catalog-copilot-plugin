<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;

/**
 * @extends EntityRepository<AiProvenanceInterface>
 */
class AiProvenanceRepository extends EntityRepository implements AiProvenanceRepositoryInterface
{
    public function findOneByTranslationAndField(ProductTranslationInterface $translation, string $field): ?AiProvenanceInterface
    {
        if (null === $translation->getId()) {
            return null;
        }

        $provenance = $this->findOneBy(['translation' => $translation, 'field' => $field]);

        return $provenance instanceof AiProvenanceInterface ? $provenance : null;
    }

    public function findByTranslation(ProductTranslationInterface $translation): array
    {
        if (null === $translation->getId()) {
            return [];
        }

        $provenances = [];
        foreach ($this->findBy(['translation' => $translation]) as $provenance) {
            $provenances[$provenance->getField()] = $provenance;
        }

        return $provenances;
    }
}
