<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

class AiSuggestionRepository extends EntityRepository implements AiSuggestionRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('product')
            ->innerJoin('o.product', 'product')
        ;
    }

    public function findPendingForProduct(ProductInterface $product): array
    {
        return $this->getSuggestions(
            $this->createQueryBuilder('o')
                ->andWhere('o.product = :product')
                ->andWhere('o.status = :pending')
                ->setParameter('product', $product)
                ->setParameter('pending', AiSuggestionInterface::STATUS_PENDING)
                ->orderBy('o.localeCode', 'ASC')
                ->addOrderBy('o.id', 'ASC'),
        );
    }

    public function findPendingFor(ProductInterface $product, string $localeCode, string $field): array
    {
        return $this->getSuggestions(
            $this->createQueryBuilder('o')
                ->andWhere('o.product = :product')
                ->andWhere('o.localeCode = :localeCode')
                ->andWhere('o.field = :field')
                ->andWhere('o.status = :pending')
                ->setParameter('product', $product)
                ->setParameter('localeCode', $localeCode)
                ->setParameter('field', $field)
                ->setParameter('pending', AiSuggestionInterface::STATUS_PENDING)
                ->orderBy('o.id', 'ASC'),
        );
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->getSuggestions(
            $this->createQueryBuilder('o')
                ->andWhere('o.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('o.id', 'ASC'),
        );
    }

    /** @return list<AiSuggestionInterface> */
    private function getSuggestions(QueryBuilder $queryBuilder): array
    {
        /** @var list<AiSuggestionInterface> $suggestions */
        $suggestions = $queryBuilder->getQuery()->getResult();

        return $suggestions;
    }
}
