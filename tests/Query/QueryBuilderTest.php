<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Query;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use MethorZ\SwiftDb\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        // Create a mock connection
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $this->builder = new QueryBuilder($connection);
    }

    // =========================================================================
    // BASIC SELECT TESTS
    // =========================================================================

    public function testSimpleSelect(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select(['product_id', 'product_name'])
            ->toSql();

        $this->assertStringContainsString('SELECT product_id, product_name', $sql);
        $this->assertStringContainsString('FROM `product`', $sql);
    }

    public function testSelectWithStringColumns(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('product_id')
            ->toSql();

        $this->assertStringContainsString('SELECT product_id', $sql);
    }

    public function testSelectDistinct(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('product_category_id')
            ->distinct()
            ->toSql();

        $this->assertStringContainsString('SELECT DISTINCT', $sql);
    }

    public function testAddSelect(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('product_id')
            ->addSelect('product_name', 'product_price')
            ->toSql();

        $this->assertStringContainsString('product_id', $sql);
        $this->assertStringContainsString('product_name', $sql);
        $this->assertStringContainsString('product_price', $sql);
    }

    public function testSelectRaw(): void
    {
        $sql = $this->builder
            ->table('product')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(product_price) as avg_price')
            ->toSql();

        $this->assertStringContainsString('COUNT(*) as total', $sql);
        $this->assertStringContainsString('AVG(product_price) as avg_price', $sql);
    }

    public function testFromAlias(): void
    {
        $sql = $this->builder
            ->from('product')
            ->select('*')
            ->toSql();

        $this->assertStringContainsString('FROM `product`', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - Basic Operators
    // =========================================================================

    public function testSelectWithWhere(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_price', '>', 10)
            ->toSql();

        $this->assertStringContainsString('WHERE `product_price` > ?', $sql);
    }

    public function testSelectWithMultipleWhere(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_price', '>', 10)
            ->where('product_stock', '>=', 5)
            ->toSql();

        $this->assertStringContainsString('WHERE `product_price` > ?', $sql);
        $this->assertStringContainsString('AND `product_stock` >= ?', $sql);
    }

    public function testSelectWithOrWhere(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_price', '>', 10)
            ->orWhere('product_featured', 1)
            ->toSql();

        $this->assertStringContainsString('WHERE `product_price` > ?', $sql);
        $this->assertStringContainsString('OR `product_featured` = ?', $sql);
    }

    public function testImplicitEqualsOperator(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_id', 123);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_id` = ?', $sql);
        $this->assertEquals([123], $bindings);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - Array Syntax
    // =========================================================================

    public function testArrayWhereConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where([
                'product_active' => true,
                'product_status' => 'published',
            ]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('AND `product_status` = ?', $sql);
        $this->assertEquals([true, 'published'], $bindings);
    }

    public function testArrayWhereWithOperators(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where([
                ['product_price', '>', 10],
                ['product_stock', '>=', 5],
            ]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_price` > ?', $sql);
        $this->assertStringContainsString('AND `product_stock` >= ?', $sql);
        $this->assertEquals([10, 5], $bindings);
    }

    public function testOrWhereWithArray(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhere([
                'product_featured' => true,
            ]);

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR `product_featured` = ?', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - Nested/Closure
    // =========================================================================

    public function testNestedWhereConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->where(function ($q) {
                $q->where('product_price', '<', 10)
                  ->orWhere('product_featured', true);
            });

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('AND (`product_price` < ?', $sql);
        $this->assertStringContainsString('OR `product_featured` = ?', $sql);
        $this->assertEquals([true, 10, true], $bindings);
    }

    public function testDeeplyNestedConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('product_category_id', 5)
                       ->where('product_price', '>', 100);
                })
                ->orWhere(function ($q2) {
                    $q2->where('product_featured', true)
                       ->where('product_stock', '>', 0);
                });
            });

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('((`product_category_id` = ?', $sql);
        $this->assertCount(5, $bindings);
    }

    public function testOrWhereWithClosure(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhere(function ($q) {
                $q->where('product_featured', true)
                  ->where('product_price', '<', 50);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR (`product_featured` = ?', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - IN / NOT IN
    // =========================================================================

    public function testSelectWithWhereIn(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereIn('product_id', [1, 2, 3]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_id` IN (?, ?, ?)', $sql);
        $this->assertEquals([1, 2, 3], $bindings);
    }

    public function testSelectWithWhereNotIn(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotIn('product_status', ['deleted', 'archived']);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_status` NOT IN (?, ?)', $sql);
        $this->assertEquals(['deleted', 'archived'], $bindings);
    }

    public function testEmptyWhereInProducesFalseCondition(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereIn('product_id', []);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('1 = 0', $sql);
        $this->assertEmpty($bindings);
    }

    public function testEmptyWhereNotInProducesTrueCondition(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotIn('product_id', []);

        $sql = $builder->toSql();

        $this->assertStringContainsString('1 = 1', $sql);
    }

    public function testWhereInWithSubquery(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereIn('product_category_id', function ($sub) {
                $sub->table('category')
                    ->select('category_id')
                    ->where('category_active', true);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_category_id` IN (SELECT', $sql);
        $this->assertStringContainsString('FROM `category`', $sql);
    }

    public function testWhereNotInWithSubquery(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotIn('product_category_id', function ($sub) {
                $sub->table('category')
                    ->select('category_id')
                    ->where('category_deleted', true);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_category_id` NOT IN (SELECT', $sql);
    }

    public function testOrWhereIn(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereIn('product_id', [1, 2, 3]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR `product_id` IN (?, ?, ?)', $sql);
        $this->assertEquals([true, 1, 2, 3], $bindings);
    }

    public function testOrWhereNotIn(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereNotIn('product_status', ['deleted', 'archived']);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('OR `product_status` NOT IN (?, ?)', $sql);
        $this->assertEquals([true, 'deleted', 'archived'], $bindings);
    }

    public function testOrWhereInWithSubquery(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereIn('product_category_id', function ($sub) {
                $sub->table('category')
                    ->select('category_id')
                    ->where('category_featured', true);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR `product_category_id` IN (SELECT', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - NULL
    // =========================================================================

    public function testSelectWithWhereNull(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->whereNull('product_deleted_at')
            ->toSql();

        $this->assertStringContainsString('WHERE `product_deleted_at` IS NULL', $sql);
    }

    public function testSelectWithWhereNotNull(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotNull('product_price')
            ->toSql();

        $this->assertStringContainsString('WHERE `product_price` IS NOT NULL', $sql);
    }

    public function testOrWhereNull(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereNull('product_price')
            ->toSql();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR `product_price` IS NULL', $sql);
    }

    public function testOrWhereNotNull(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', false)
            ->orWhereNotNull('product_featured')
            ->toSql();

        $this->assertStringContainsString('OR `product_featured` IS NOT NULL', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - BETWEEN
    // =========================================================================

    public function testSelectWithWhereBetween(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereBetween('product_price', 10.0, 100.0);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_price` BETWEEN ? AND ?', $sql);
        $this->assertEquals([10.0, 100.0], $bindings);
    }

    public function testWhereNotBetween(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotBetween('product_price', 10.0, 50.0);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_price` NOT BETWEEN ? AND ?', $sql);
        $this->assertEquals([10.0, 50.0], $bindings);
    }

    public function testOrWhereBetween(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_featured', true)
            ->orWhereBetween('product_price', 10.0, 50.0);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_featured` = ?', $sql);
        $this->assertStringContainsString('OR `product_price` BETWEEN ? AND ?', $sql);
        $this->assertEquals([true, 10.0, 50.0], $bindings);
    }

    public function testOrWhereNotBetween(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereNotBetween('product_stock', 10, 50);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('OR `product_stock` NOT BETWEEN ? AND ?', $sql);
        $this->assertEquals([true, 10, 50], $bindings);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - LIKE
    // =========================================================================

    public function testSelectWithWhereLike(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereLike('product_name', '%widget%');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_name` LIKE ?', $sql);
        $this->assertEquals(['%widget%'], $bindings);
    }

    public function testOrWhereLike(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereLike('product_name', '%test%');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR `product_name` LIKE ?', $sql);
        $this->assertEquals([true, '%test%'], $bindings);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - COLUMN COMPARISON
    // =========================================================================

    public function testWhereColumn(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->whereColumn('product_created', 'product_updated')
            ->toSql();

        $this->assertStringContainsString('WHERE `product_created` = `product_updated`', $sql);
    }

    public function testWhereColumnWithOperator(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->whereColumn('product_price', '>', 'product_cost')
            ->toSql();

        $this->assertStringContainsString('WHERE `product_price` > `product_cost`', $sql);
    }

    public function testOrWhereColumn(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereColumn('product_created', 'product_updated')
            ->toSql();

        $this->assertStringContainsString('OR `product_created` = `product_updated`', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - EXISTS
    // =========================================================================

    public function testWhereExists(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereExists(function ($sub) {
                $sub->table('inventory')
                    ->select('1')
                    ->whereColumn('inventory.product_id', 'product.product_id')
                    ->where('inventory.quantity', '>', 0);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE EXISTS (SELECT', $sql);
        $this->assertStringContainsString('FROM `inventory`', $sql);
    }

    public function testWhereNotExists(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereNotExists(function ($sub) {
                $sub->table('order_item')
                    ->select('1')
                    ->whereColumn('order_item.product_id', 'product.product_id');
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE NOT EXISTS (SELECT', $sql);
    }

    public function testOrWhereExists(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_featured', true)
            ->orWhereExists(function ($sub) {
                $sub->table('inventory')
                    ->select('1')
                    ->whereColumn('inventory.product_id', 'product.product_id')
                    ->where('inventory.quantity', '>', 100);
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_featured` = ?', $sql);
        $this->assertStringContainsString('OR EXISTS (SELECT', $sql);
    }

    public function testOrWhereNotExists(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereNotExists(function ($sub) {
                $sub->table('order_item')
                    ->select('1')
                    ->whereColumn('order_item.product_id', 'product.product_id');
            });

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR NOT EXISTS (SELECT', $sql);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - RAW
    // =========================================================================

    public function testWhereRaw(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->whereRaw('product_price * product_quantity > ?', [1000]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE product_price * product_quantity > ?', $sql);
        $this->assertEquals([1000], $bindings);
    }

    public function testOrWhereRaw(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->orWhereRaw('product_price * ? < product_cost', [0.8]);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('OR product_price * ? < product_cost', $sql);
        $this->assertEquals([true, 0.8], $bindings);
    }

    // =========================================================================
    // JOIN TESTS
    // =========================================================================

    public function testSelectWithJoin(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select(['product.product_id', 'category.category_name'])
            ->join('category', 'product.product_category_id', '=', 'category.category_id')
            ->toSql();

        $this->assertStringContainsString(
            'INNER JOIN `category` ON product.product_category_id = category.category_id',
            $sql,
        );
    }

    public function testJoinWithImplicitEquals(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->join('category', 'product.product_category_id', 'category.category_id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN `category`', $sql);
    }

    public function testSelectWithLeftJoin(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->leftJoin('category', 'product.product_category_id', '=', 'category.category_id')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN `category`', $sql);
    }

    public function testSelectWithRightJoin(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->rightJoin('category', 'product.product_category_id', '=', 'category.category_id')
            ->toSql();

        $this->assertStringContainsString('RIGHT JOIN `category`', $sql);
    }

    public function testJoinWithClosure(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->join('discount', function ($join) {
                $join->on('product.product_id', 'discount.product_id')
                     ->where('discount.active', true);
            })
            ->toSql();

        $this->assertStringContainsString('INNER JOIN `discount` ON', $sql);
        $this->assertStringContainsString('product.product_id = discount.product_id', $sql);
    }

    public function testLeftJoinWithClosure(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->leftJoin('inventory', function ($join) {
                $join->on('product.product_id', 'inventory.product_id')
                     ->where('inventory.quantity', '>', 0)
                     ->whereNull('inventory.deleted_at');
            })
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN `inventory` ON', $sql);
    }

    // =========================================================================
    // ORDER BY TESTS
    // =========================================================================

    public function testSelectWithOrderBy(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->orderBy('product_created', 'DESC')
            ->orderBy('product_name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY `product_created` DESC, `product_name` ASC', $sql);
    }

    public function testOrderByDesc(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->orderByDesc('product_created')
            ->toSql();

        $this->assertStringContainsString('ORDER BY `product_created` DESC', $sql);
    }

    public function testOrderByAsc(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->orderByAsc('product_name')
            ->toSql();

        $this->assertStringContainsString('ORDER BY `product_name` ASC', $sql);
    }

    public function testOrderByWithArrayIndexed(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->orderBy([
                ['product_featured', 'DESC'],
                ['product_name', 'ASC'],
            ])
            ->toSql();

        $this->assertStringContainsString('ORDER BY `product_featured` DESC, `product_name` ASC', $sql);
    }

    public function testOrderByWithArrayAssociative(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->orderBy([
                'product_featured' => 'DESC',
                'product_name' => 'ASC',
            ])
            ->toSql();

        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('DESC', $sql);
        $this->assertStringContainsString('ASC', $sql);
    }

    // =========================================================================
    // GROUP BY TESTS
    // =========================================================================

    public function testSelectWithGroupBy(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select(['product_category_id', 'COUNT(*) as count'])
            ->groupBy('product_category_id')
            ->toSql();

        $this->assertStringContainsString('GROUP BY `product_category_id`', $sql);
    }

    public function testSelectWithMultipleGroupBy(): void
    {
        $sql = $this->builder
            ->table('order_item')
            ->select(['order_id', 'product_id', 'SUM(quantity) as total'])
            ->groupBy(['order_id', 'product_id'])
            ->toSql();

        $this->assertStringContainsString('GROUP BY `order_id`, `product_id`', $sql);
    }

    // =========================================================================
    // LIMIT / OFFSET TESTS
    // =========================================================================

    public function testSelectWithLimit(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testSelectWithOffsetOnly(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->offset(10)
            ->toSql();

        $this->assertStringContainsString('OFFSET 10', $sql);
    }

    public function testTakeAndSkip(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->take(10)
            ->skip(20)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    // =========================================================================
    // UNION TESTS
    // =========================================================================

    public function testUnion(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);
        $secondQuery = new QueryBuilder($connection);
        $secondQuery->table('archived_product')->select('*');

        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->union($secondQuery)
            ->toSql();

        $this->assertStringContainsString('SELECT * FROM `product`', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT * FROM `archived_product`', $sql);
    }

    public function testUnionAll(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);
        $secondQuery = new QueryBuilder($connection);
        $secondQuery->table('archived_product')->select('*');

        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->unionAll($secondQuery)
            ->toSql();

        $this->assertStringContainsString('UNION ALL', $sql);
    }

    // =========================================================================
    // CONDITIONAL BUILDING TESTS
    // =========================================================================

    public function testWhenConditional(): void
    {
        $categoryId = 5;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->when($categoryId, function ($q, $id) {
                $q->where('product_category_id', $id);
            });

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_category_id` = ?', $sql);
        $this->assertEquals([5], $bindings);
    }

    public function testWhenConditionalFalse(): void
    {
        $categoryId = null;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->when($categoryId, function ($q, $id) {
                $q->where('product_category_id', $id);
            });

        $sql = $builder->toSql();

        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testWhenConditionalWithDefault(): void
    {
        $categoryId = null;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->when(
                $categoryId,
                fn ($q, $id) => $q->where('product_category_id', $id),
                fn ($q) => $q->where('product_featured', true),
            );

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        // Default callback should be executed when condition is falsy
        $this->assertStringContainsString('WHERE `product_featured` = ?', $sql);
        $this->assertEquals([true], $bindings);
    }

    public function testUnlessConditional(): void
    {
        $showInactive = false;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->unless($showInactive, fn (QueryBuilder $q): QueryBuilder => $q->where('product_active', true));

        $sql = $builder->toSql();

        // Condition should be applied when value is falsy
        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
    }

    public function testUnlessConditionalTrue(): void
    {
        $showInactive = true;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->unless($showInactive, fn (QueryBuilder $q): QueryBuilder => $q->where('product_active', true));

        $sql = $builder->toSql();

        // Condition should NOT be applied when value is truthy
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testTap(): void
    {
        $logged = false;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->tap(function ($q) use (&$logged) {
                $logged = true;
            })
            ->where('product_active', true);

        $this->assertTrue($logged);
        $this->assertStringContainsString('WHERE', $builder->toSql());
    }

    // =========================================================================
    // CHAINED / COMPLEX QUERY TESTS
    // =========================================================================

    public function testChainedWhereConditions(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_price', '>', 10)
            ->where('product_stock', '>', 0)
            ->orWhere('product_featured', 1)
            ->whereNull('product_deleted_at')
            ->whereNotNull('product_sku');

        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE `product_price` > ?', $sql);
        $this->assertStringContainsString('AND `product_stock` > ?', $sql);
        $this->assertStringContainsString('OR `product_featured` = ?', $sql);
        $this->assertStringContainsString('AND `product_deleted_at` IS NULL', $sql);
        $this->assertStringContainsString('AND `product_sku` IS NOT NULL', $sql);
    }

    public function testComplexQueryWithJoinsAndConditions(): void
    {
        $sql = $this->builder
            ->table('product')
            ->select(['product.*', 'category.category_name'])
            ->join('category', 'product.product_category_id', '=', 'category.category_id')
            ->where('product_price', '>=', 10)
            ->whereBetween('product_created', '2024-01-01', '2024-12-31')
            ->orderBy('product_price', 'DESC')
            ->limit(20)
            ->toSql();

        $this->assertStringContainsString('SELECT product.*, category.category_name', $sql);
        $this->assertStringContainsString('INNER JOIN `category`', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('BETWEEN', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 20', $sql);
    }

    public function testRealWorldSearchQuery(): void
    {
        $search = 'widget';
        $categoryId = 5;
        $minPrice = 10;
        $maxPrice = 100;
        $inStock = true;

        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->whereLike('product_name', "%{$search}%")
                       ->orWhere('product_sku', 'LIKE', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($q, $id) => $q->where('product_category_id', $id))
            ->when($minPrice, fn ($q, $min) => $q->where('product_price', '>=', $min))
            ->when($maxPrice, fn ($q, $max) => $q->where('product_price', '<=', $max))
            ->when($inStock, fn ($q) => $q->where('product_stock', '>', 0))
            ->orderByAsc('product_name')
            ->limit(50);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $this->assertStringContainsString('WHERE `product_active` = ?', $sql);
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('product_category_id', $sql);
        $this->assertStringContainsString('product_price', $sql);
        $this->assertStringContainsString('product_stock', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 50', $sql);

        // Verify we have all bindings
        $this->assertGreaterThanOrEqual(6, count($bindings));
    }

    // =========================================================================
    // UTILITY METHOD TESTS
    // =========================================================================

    public function testReset(): void
    {
        $this->builder
            ->table('product')
            ->select('*')
            ->where('product_id', 1)
            ->reset();

        $sql = $this->builder
            ->table('product')
            ->select('*')
            ->toSql();

        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testClone(): void
    {
        $original = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_active', true);

        $cloned = $original->clone();
        $cloned->where('product_featured', true);

        $originalSql = $original->toSql();
        $clonedSql = $cloned->toSql();

        $this->assertStringNotContainsString('product_featured', $originalSql);
        $this->assertStringContainsString('product_featured', $clonedSql);
    }

    public function testNewQuery(): void
    {
        $newQuery = $this->builder->newQuery();

        $this->assertInstanceOf(QueryBuilder::class, $newQuery);
        $this->assertNotSame($this->builder, $newQuery);
    }

    public function testGetBindingsReturnsAllBindings(): void
    {
        $builder = $this->builder
            ->table('product')
            ->select('*')
            ->where('product_price', '>', 10)
            ->whereIn('product_category_id', [1, 2, 3])
            ->whereBetween('product_stock', 5, 100);

        $bindings = $builder->getBindings();

        $this->assertContains(10, $bindings);
        $this->assertContains(1, $bindings);
        $this->assertContains(2, $bindings);
        $this->assertContains(3, $bindings);
        $this->assertContains(5, $bindings);
        $this->assertContains(100, $bindings);
    }

    // =========================================================================
    // SQL STATEMENT STRUCTURE TESTS
    // =========================================================================

    public function testUpdateQueryStructure(): void
    {
        $builder = $this->builder
            ->table('product')
            ->where('product_id', 1);

        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE `product_id` = ?', $sql);
    }

    public function testDeleteQueryStructure(): void
    {
        $builder = $this->builder
            ->table('product')
            ->where('product_id', 1);

        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE `product_id` = ?', $sql);
    }
}
