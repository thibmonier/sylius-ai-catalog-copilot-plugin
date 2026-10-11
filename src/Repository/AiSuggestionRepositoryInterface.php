<?php

declare(strict_types=1);

namespace Tmonier\SyliusAiCatalogCopilotPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusAiCatalogCopilotPlugin\Entity\AiSuggestionInterface;

/**
 * @extends RepositoryInterface<AiSuggestionInterface>
 */
interface AiSuggestionRepositoryInterface extends RepositoryInterface
{
    /** Query builder of the admin grid. */
    public function createListQueryBuilder(): QueryBuilder;

    /** @return list<AiSuggestionInterface> */
    public function findPendingForProduct(ProductInterface $product): array;

    /** @return list<AiSuggestionInterface> */
    public function findPendingFor(ProductInterface $product, string $localeCode, string $field): array;

    /**
     * @param list<int> $ids
     *
     * @return list<AiSuggestionInterface>
     */
    public function findByIds(array $ids): array;
}
