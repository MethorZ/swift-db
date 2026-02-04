<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Query;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use MethorZ\SwiftDb\Pagination\PaginatedResult;
use MethorZ\SwiftDb\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for QueryBuilder pagination
 */
final class QueryBuilderPaginationTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);
        $this->builder = new QueryBuilder($connection);
    }

    // =========================================================================
    // SQL GENERATION TESTS
    // =========================================================================

    public function testPaginateFirstPageHasZeroOffset(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*');

        // We need to test the SQL that would be generated
        // Since paginate executes the query, we test limit/offset directly
        $clonedBuilder = $builder->clone()
            ->limit(15)
            ->offset(0);

        $sql = $clonedBuilder->toSql();

        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertStringContainsString('OFFSET 0', $sql);
    }

    public function testPaginateSecondPageOffset(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->limit(15)
            ->offset(15); // Page 2 = (2-1) * 15

        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertStringContainsString('OFFSET 15', $sql);
    }

    public function testPaginateThirdPageOffset(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->limit(10)
            ->offset(20); // Page 3 with 10 per page = (3-1) * 10

        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testPaginateWithWhereClause(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->limit(15)
            ->offset(0);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertEquals([true], $bindings);
    }

    public function testPaginateWithOrderBy(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->orderBy('product_created', 'DESC')
            ->limit(20)
            ->offset(40);

        $sql = $builder->toSql();

        $this->assertStringContainsString('ORDER BY `product_created` DESC', $sql);
        $this->assertStringContainsString('LIMIT 20', $sql);
        $this->assertStringContainsString('OFFSET 40', $sql);
    }

    public function testPaginateWithJoin(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select(['product.*', 'category.category_name'])
            ->leftJoin('category', 'product.category_id', 'category.category_id')
            ->limit(15)
            ->offset(0);

        $sql = $builder->toSql();

        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('LIMIT 15', $sql);
    }

    public function testPaginateWithMultipleConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->where('product_price', '>', 10)
            ->whereBetween('product_stock', 1, 100)
            ->limit(25)
            ->offset(50);

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('LIMIT 25', $sql);
        $this->assertStringContainsString('OFFSET 50', $sql);
    }

    // =========================================================================
    // PARAMETER VALIDATION TESTS
    // =========================================================================

    public function testLimitWithZeroDefaultsToProvided(): void
    {
        // Testing the limit method directly
        $builder = $this->builder
            ->table('product')
            ->limit(1);

        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 1', $sql);
    }

    public function testOffsetWithZero(): void
    {
        $builder = $this->builder
            ->table('product')
            ->limit(10)
            ->offset(0);

        $sql = $builder->toSql();

        $this->assertStringContainsString('OFFSET 0', $sql);
    }

    // =========================================================================
    // RETURN TYPE TESTS
    // =========================================================================

    public function testPaginatedResultClassExists(): void
    {
        $this->assertTrue(class_exists(PaginatedResult::class));
    }

    public function testPaginatedResultImplementsCountable(): void
    {
        $result = new PaginatedResult([], 100, 15, 1);

        $this->assertInstanceOf(\Countable::class, $result);
    }

    public function testPaginatedResultImplementsIteratorAggregate(): void
    {
        $result = new PaginatedResult([], 100, 15, 1);

        $this->assertInstanceOf(\IteratorAggregate::class, $result);
    }

    // =========================================================================
    // CLONE BEHAVIOR TESTS
    // =========================================================================

    public function testCloneCreatesIndependentBuilder(): void
    {
        $builder = $this->builder
            ->table('product')
            ->where('product_active', true);

        $cloned = $builder->clone();
        $cloned->where('product_price', '>', 100);

        $originalSql = $builder->toSql();
        $clonedSql = $cloned->toSql();

        // Original should not have the price condition
        $this->assertStringNotContainsString('product_price', $originalSql);
        // Cloned should have both conditions
        $this->assertStringContainsString('product_price', $clonedSql);
    }

    public function testClonePreservesTableAndConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select(['product_id', 'product_name'])
            ->where('product_active', true)
            ->orderBy('product_name', 'ASC');

        $cloned = $builder->clone();
        $sql = $cloned->toSql();

        $this->assertStringContainsString('FROM `product`', $sql);
        $this->assertStringContainsString('product_active', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
    }
}
