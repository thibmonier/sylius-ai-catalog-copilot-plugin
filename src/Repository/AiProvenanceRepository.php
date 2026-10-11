<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiProvenanceInterface;

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

        /** @var list<AiProvenanceInterface> $found */
        $found = $this->findBy(['translation' => $translation]);
        $provenances = [];
        foreach ($found as $provenance) {
            $provenances[$provenance->getField()] = $provenance;
        }

        return $provenances;
    }
}
