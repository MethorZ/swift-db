<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Repository;

use MethorZ\SwiftDb\Repository\AbstractRepository;
use MethorZ\SwiftDb\Tests\Integration\Entity\Product;

/**
 * Product repository for integration tests
 *
 * @extends AbstractRepository<Product>
 */
class ProductRepository extends AbstractRepository
{
    public function getTableName(): string
    {
        return 'product';
    }

    public function getEntityClass(): string
    {
        return Product::class;
    }

    /**
     * Find active products
     *
     * @return array<Product>
     */
    public function findActive(): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->orderBy('product_created', 'DESC')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Find by category
     *
     * @return array<Product>
     */
    public function findByCategory(int $categoryId): array
    {
        $rows = $this->query()
            ->where('product_category_id', $categoryId)
            ->orderBy('product_name', 'ASC')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }
}
