<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Repository;

use MethorZ\SwiftDb\Repository\AbstractRepository;
use MethorZ\SwiftDb\Tests\Entity\TestProduct;

/**
 * Test repository for unit testing AbstractRepository
 *
 * @extends AbstractRepository<TestProduct>
 */
final class TestProductRepository extends AbstractRepository
{
    public function getTableName(): string
    {
        return 'product';
    }

    public function getEntityClass(): string
    {
        return TestProduct::class;
    }

    /**
     * Expose protected method for testing
     *
     * @param array<string, mixed> $row
     */
    public function exposeHydrateEntity(array $row): TestProduct
    {
        return $this->hydrateEntity($row);
    }
}
