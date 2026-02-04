<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Repository;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use MethorZ\SwiftDb\Pagination\PaginatedResult;
use MethorZ\SwiftDb\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbstractRepository pagination
 */
final class AbstractRepositoryPaginationTest extends TestCase
{
    private Connection $connection;

    private TestProductRepository $repository;

    protected function setUp(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $this->connection = new Connection($config);
        $this->repository = new TestProductRepository($this->connection);
    }

    // =========================================================================
    // PAGINATE METHOD TESTS
    // =========================================================================

    public function testQueryReturnedByRepositoryIsQueryBuilder(): void
    {
        $query = $this->repository->query();

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function testPaginatedResultClassProperties(): void
    {
        // Test that PaginatedResult has the expected properties
        $result = new PaginatedResult(
            items: [['id' => 1, 'name' => 'Test']],
            total: 100,
            perPage: 15,
            currentPage: 1,
        );

        $this->assertEquals([['id' => 1, 'name' => 'Test']], $result->items);
        $this->assertEquals(100, $result->total);
        $this->assertEquals(15, $result->perPage);
        $this->assertEquals(1, $result->currentPage);
    }

    public function testPaginatedResultHelperMethods(): void
    {
        $result = new PaginatedResult(
            items: [['id' => 1]],
            total: 100,
            perPage: 10,
            currentPage: 5,
        );

        // lastPage = ceil(100 / 10) = 10
        $this->assertEquals(10, $result->lastPage());

        // currentPage (5) < lastPage (10)
        $this->assertTrue($result->hasMorePages());

        // currentPage (5) > 1
        $this->assertTrue($result->hasPreviousPage());

        // Has items
        $this->assertFalse($result->isEmpty());
        $this->assertTrue($result->isNotEmpty());

        // firstItem = (5-1) * 10 + 1 = 41
        $this->assertEquals(41, $result->firstItem());

        // lastItem = 41 + 1 - 1 = 41 (only 1 item)
        $this->assertEquals(41, $result->lastItem());
    }

    public function testPaginatedResultOnFirstPage(): void
    {
        $result = new PaginatedResult(
            items: array_map(fn ($i) => ['id' => $i], range(1, 10)),
            total: 50,
            perPage: 10,
            currentPage: 1,
        );

        $this->assertFalse($result->hasPreviousPage());
        $this->assertTrue($result->hasMorePages());
        $this->assertEquals(1, $result->firstItem());
        $this->assertEquals(10, $result->lastItem());
        $this->assertEquals(5, $result->lastPage());
    }

    public function testPaginatedResultOnLastPage(): void
    {
        $result = new PaginatedResult(
            items: [['id' => 46], ['id' => 47], ['id' => 48], ['id' => 49], ['id' => 50]],
            total: 50,
            perPage: 10,
            currentPage: 5,
        );

        $this->assertTrue($result->hasPreviousPage());
        $this->assertFalse($result->hasMorePages());
        $this->assertEquals(41, $result->firstItem());
        $this->assertEquals(45, $result->lastItem());
    }

    public function testPaginatedResultEmpty(): void
    {
        $result = new PaginatedResult(
            items: [],
            total: 0,
            perPage: 10,
            currentPage: 1,
        );

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->isNotEmpty());
        $this->assertNull($result->firstItem());
        $this->assertNull($result->lastItem());
        $this->assertEquals(1, $result->lastPage());
        $this->assertFalse($result->hasMorePages());
        $this->assertFalse($result->hasPreviousPage());
    }

    public function testPaginatedResultCountable(): void
    {
        $result = new PaginatedResult(
            items: [['id' => 1]],
            total: 250,
            perPage: 25,
            currentPage: 1,
        );

        // count() returns total, not items count
        $this->assertEquals(250, count($result));
        $this->assertEquals(250, $result->count());
    }

    public function testPaginatedResultIterable(): void
    {
        $items = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
            ['id' => 3, 'name' => 'Third'],
        ];

        $result = new PaginatedResult(
            items: $items,
            total: 100,
            perPage: 10,
            currentPage: 1,
        );

        $collected = [];
        foreach ($result as $item) {
            $collected[] = $item;
        }

        $this->assertEquals($items, $collected);
        $this->assertCount(3, $collected);
    }

    // =========================================================================
    // INTEGRATION WITH REPOSITORY TESTS
    // =========================================================================

    public function testRepositoryQueryBuilderCanBeChainedWithPagination(): void
    {
        $query = $this->repository->query()
            ->where('product_active', true)
            ->orderBy('product_name', 'ASC');

        // Verify the query builder works correctly before pagination
        $sql = $query->toSql();

        $this->assertStringContainsString('FROM `product`', $sql);
        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('ORDER BY `product_name` ASC', $sql);
    }

    public function testQueryBuilderWithLimitAndOffset(): void
    {
        $query = $this->repository->query()
            ->limit(15)
            ->offset(30);

        $sql = $query->toSql();

        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertStringContainsString('OFFSET 30', $sql);
    }
}
