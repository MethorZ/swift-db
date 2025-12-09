<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Query;

use MethorZ\SwiftDb\Query\QueryBuilder;
use MethorZ\SwiftDb\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for QueryBuilder
 */
final class QueryBuilderIntegrationTest extends IntegrationTestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new QueryBuilder($this->connection, $this->logger);
    }

    // =========================================================================
    // BASIC SELECT TESTS
    // =========================================================================

    public function testSimpleSelect(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product_id', 'product_name', 'product_price'])
            ->get();

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('product_id', $results[0]);
        $this->assertArrayHasKey('product_name', $results[0]);
        $this->assertArrayHasKey('product_price', $results[0]);
    }

    public function testSelectFirst(): void
    {
        $this->seedProducts();

        $result = $this->builder
            ->table('product')
            ->select('*')
            ->orderBy('product_id', 'ASC')
            ->first();

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['product_id']);
    }

    public function testSelectFirstReturnsNullWhenEmpty(): void
    {
        $result = $this->builder
            ->table('product')
            ->first();

        $this->assertNull($result);
    }

    public function testSelectDistinct(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select('product_category_id')
            ->distinct()
            ->get();

        $this->assertCount(2, $results); // Categories 1 and 2
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - BASIC
    // =========================================================================

    public function testWhereEquals(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['product_id']);
    }

    public function testWhereWithImplicitEquals(): void
    {
        $this->seedProducts();

        // Laravel-style: 2 args means implicit '='
        $results = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['product_id']);
    }

    public function testWhereGreaterThan(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->where('product_price', '>', 25.00)
            ->get();

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $price = is_numeric($result['product_price']) ? (float) $result['product_price'] : 0.0;
            $this->assertGreaterThan(25.00, $price);
        }
    }

    public function testOrWhere(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->orWhere('product_id', 3)
            ->get();

        $this->assertCount(2, $results);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - ARRAY SYNTAX
    // =========================================================================

    public function testWhereWithArraySyntax(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->where([
                'product_active' => 1,
                'product_category_id' => 1,
            ])
            ->get();

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(1, $result['product_category_id']);
        }
    }

    public function testWhereWithArrayOperatorSyntax(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->where([
                ['product_price', '>', 20],
                ['product_stock', '>', 0],
            ])
            ->get();

        $this->assertCount(1, $results);
        $price = is_numeric($results[0]['product_price']) ? (float) $results[0]['product_price'] : 0.0;
        $this->assertEqualsWithDelta(29.99, $price, 0.01);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - NESTED/CLOSURE
    // =========================================================================

    public function testNestedWhereConditions(): void
    {
        $this->seedProducts();

        // WHERE active = 1 AND (price < 25 OR stock = 0)
        $results = $this->builder
            ->table('product')
            ->where('product_active', 1)
            ->where(function ($q) {
                $q->where('product_price', '<', 25)
                  ->orWhere('product_stock', 0);
            })
            ->get();

        $this->assertCount(2, $results);
    }

    public function testDeeplyNestedConditions(): void
    {
        $this->seedProducts();

        // Complex nested condition
        $results = $this->builder
            ->table('product')
            ->where('product_active', 1)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('product_category_id', 1)
                       ->where('product_price', '>', 25);
                })
                ->orWhere(function ($q2) {
                    $q2->where('product_stock', 0);
                });
            })
            ->get();

        // Should match: Product 2 (cat 1, price > 25) OR Product 3 (stock = 0)
        $this->assertCount(2, $results);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - IN / NOT IN
    // =========================================================================

    public function testWhereIn(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereIn('product_id', [1, 3])
            ->get();

        $this->assertCount(2, $results);
    }

    public function testWhereNotIn(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereNotIn('product_id', [1, 3])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]['product_id']);
    }

    public function testEmptyWhereInReturnsNoResults(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereIn('product_id', [])
            ->get();

        $this->assertCount(0, $results);
    }

    public function testEmptyWhereNotInReturnsAllResults(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereNotIn('product_id', [])
            ->get();

        $this->assertCount(3, $results);
    }

    public function testWhereInWithSubquery(): void
    {
        $this->seedProducts();

        // Products in categories with name starting with 'E'
        $results = $this->builder
            ->table('product')
            ->whereIn('product_category_id', function ($sub) {
                $sub->table('category')
                    ->select('category_id')
                    ->whereLike('category_name', 'E%');
            })
            ->get();

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(1, $result['product_category_id']);
        }
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - NULL
    // =========================================================================

    public function testWhereNull(): void
    {
        // Insert product without category
        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO product (product_uuid, product_name, product_price, product_stock, product_category_id)
            VALUES ('null-test-uuid', 'No Category Product', 10.00, 5, NULL)
        ");

        $this->seedProducts(); // These have categories

        $results = $this->builder
            ->table('product')
            ->whereNull('product_category_id')
            ->get();

        $this->assertCount(1, $results);
        $this->assertNull($results[0]['product_category_id']);
    }

    public function testWhereNotNull(): void
    {
        // Insert product without category
        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO product (product_uuid, product_name, product_price, product_stock, product_category_id)
            VALUES ('null-test-uuid', 'No Category Product', 10.00, 5, NULL)
        ");

        $this->seedProducts(); // These have categories

        $results = $this->builder
            ->table('product')
            ->whereNotNull('product_category_id')
            ->get();

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result['product_category_id']);
        }
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - BETWEEN
    // =========================================================================

    public function testWhereBetween(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereBetween('product_price', 20.00, 35.00)
            ->get();

        $this->assertCount(1, $results);
        $price = is_numeric($results[0]['product_price']) ? (float) $results[0]['product_price'] : 0.0;
        $this->assertEqualsWithDelta(29.99, $price, 0.01);
    }

    public function testWhereNotBetween(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereNotBetween('product_price', 20.00, 35.00)
            ->get();

        $this->assertCount(2, $results);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - LIKE
    // =========================================================================

    public function testWhereLike(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->whereLike('product_name', '%Product 1%')
            ->get();

        $this->assertCount(1, $results);
        $productName = is_string($results[0]['product_name']) ? $results[0]['product_name'] : '';
        $this->assertStringContainsString('Product 1', $productName);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS - EXISTS
    // =========================================================================

    public function testWhereExists(): void
    {
        $this->seedProducts();

        // Products that have a matching category (all seeded products do)
        $results = $this->builder
            ->table('product')
            ->whereExists(function ($sub) {
                $sub->table('category')
                    ->select('1')
                    ->whereColumn('category.category_id', 'product.product_category_id');
            })
            ->get();

        $this->assertCount(3, $results);
    }

    public function testWhereNotExists(): void
    {
        // Insert product without category
        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO product (product_uuid, product_name, product_price, product_stock, product_category_id)
            VALUES ('orphan-uuid', 'Orphan Product', 10.00, 5, 999)
        ");

        $this->seedProducts();

        // Products without a matching category
        $results = $this->builder
            ->table('product')
            ->whereNotExists(function ($sub) {
                $sub->table('category')
                    ->select('1')
                    ->whereColumn('category.category_id', 'product.product_category_id');
            })
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Orphan Product', $results[0]['product_name']);
    }

    // =========================================================================
    // CONDITIONAL BUILDING TESTS
    // =========================================================================

    public function testWhenConditionApplied(): void
    {
        $this->seedProducts();

        $categoryId = 1;

        $results = $this->builder
            ->table('product')
            ->when($categoryId, fn ($q, $id) => $q->where('product_category_id', $id))
            ->get();

        $this->assertCount(2, $results);
    }

    public function testWhenConditionNotApplied(): void
    {
        $this->seedProducts();

        $categoryId = null;

        $results = $this->builder
            ->table('product')
            ->when($categoryId, fn ($q, $id) => $q->where('product_category_id', $id))
            ->get();

        $this->assertCount(3, $results);
    }

    public function testWhenWithDefaultCallback(): void
    {
        $this->seedProducts();

        $sortBy = null;

        $results = $this->builder
            ->table('product')
            ->when(
                $sortBy === 'price',
                fn ($q) => $q->orderBy('product_price'),
                fn ($q) => $q->orderBy('product_name'),
            )
            ->get();

        // Should be sorted by name (default)
        $names = array_column($results, 'product_name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function testUnlessCondition(): void
    {
        $this->seedProducts();

        $showAll = false;

        $results = $this->builder
            ->table('product')
            ->unless($showAll, fn ($q) => $q->where('product_stock', '>', 0))
            ->get();

        // Should only show products with stock > 0
        $this->assertCount(2, $results);
    }

    // =========================================================================
    // ORDER BY TESTS
    // =========================================================================

    public function testOrderBy(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->orderBy('product_price', 'DESC')
            ->get();

        $this->assertCount(3, $results);
        $price0 = is_numeric($results[0]['product_price']) ? (float) $results[0]['product_price'] : 0.0;
        $price1 = is_numeric($results[1]['product_price']) ? (float) $results[1]['product_price'] : 0.0;
        $price2 = is_numeric($results[2]['product_price']) ? (float) $results[2]['product_price'] : 0.0;
        $this->assertEqualsWithDelta(39.99, $price0, 0.01);
        $this->assertEqualsWithDelta(29.99, $price1, 0.01);
        $this->assertEqualsWithDelta(19.99, $price2, 0.01);
    }

    public function testMultipleOrderBy(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->orderBy('product_category_id', 'ASC')
            ->orderBy('product_price', 'DESC')
            ->get();

        $this->assertCount(3, $results);
        // First two should be category 1, ordered by price desc
        $this->assertEquals(1, $results[0]['product_category_id']);
        $price = is_numeric($results[0]['product_price']) ? (float) $results[0]['product_price'] : 0.0;
        $this->assertEqualsWithDelta(29.99, $price, 0.01);
    }

    public function testOrderByAscDesc(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->orderByDesc('product_price')
            ->get();

        $this->assertCount(3, $results);
        $price0 = is_numeric($results[0]['product_price']) ? (float) $results[0]['product_price'] : 0.0;
        $this->assertEqualsWithDelta(39.99, $price0, 0.01);
    }

    // =========================================================================
    // LIMIT / OFFSET TESTS
    // =========================================================================

    public function testLimitAndOffset(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->orderBy('product_id', 'ASC')
            ->limit(2)
            ->offset(1)
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]['product_id']);
        $this->assertEquals(3, $results[1]['product_id']);
    }

    public function testTakeAndSkip(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->orderBy('product_id', 'ASC')
            ->take(1)
            ->skip(2)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(3, $results[0]['product_id']);
    }

    // =========================================================================
    // GROUP BY TESTS
    // =========================================================================

    public function testGroupBy(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product_category_id', 'COUNT(*) as count'])
            ->groupBy('product_category_id')
            ->get();

        $this->assertCount(2, $results);
    }

    // =========================================================================
    // JOIN TESTS
    // =========================================================================

    public function testJoin(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product.product_name', 'category.category_name'])
            ->join('category', 'product.product_category_id', '=', 'category.category_id')
            ->get();

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('category_name', $results[0]);
    }

    public function testJoinWithImplicitEquals(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product.product_name', 'category.category_name'])
            ->join('category', 'product.product_category_id', 'category.category_id')
            ->get();

        $this->assertCount(3, $results);
    }

    public function testLeftJoin(): void
    {
        // Insert product without category
        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO product (product_uuid, product_name, product_price, product_stock, product_category_id)
            VALUES ('no-cat-uuid', 'No Category Product', 10.00, 5, NULL)
        ");

        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product.product_name', 'category.category_name'])
            ->leftJoin('category', 'product.product_category_id', '=', 'category.category_id')
            ->get();

        $this->assertCount(4, $results);

        // Find the product without category
        $noCategoryProduct = array_filter($results, fn ($r) => $r['category_name'] === null);
        $this->assertCount(1, $noCategoryProduct);
    }

    public function testJoinWithClosure(): void
    {
        $this->seedProducts();

        $results = $this->builder
            ->table('product')
            ->select(['product.product_name', 'category.category_name'])
            ->join('category', function ($join) {
                $join->on('product.product_category_id', 'category.category_id')
                     ->where('category.category_id', 1);
            })
            ->get();

        // Only products in category 1
        $this->assertCount(2, $results);
    }

    // =========================================================================
    // AGGREGATE TESTS
    // =========================================================================

    public function testCount(): void
    {
        $this->seedProducts();

        $count = $this->builder
            ->table('product')
            ->count();

        $this->assertEquals(3, $count);
    }

    public function testCountWithWhere(): void
    {
        $this->seedProducts();

        $count = $this->builder
            ->table('product')
            ->where('product_category_id', 1)
            ->count();

        $this->assertEquals(2, $count);
    }

    public function testExists(): void
    {
        $this->seedProducts();

        $exists = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->exists();

        $this->assertTrue($exists);

        $notExists = $this->builder
            ->reset()
            ->table('product')
            ->where('product_id', 999)
            ->exists();

        $this->assertFalse($notExists);
    }

    public function testDoesntExist(): void
    {
        $this->seedProducts();

        $doesntExist = $this->builder
            ->table('product')
            ->where('product_id', 999)
            ->doesntExist();

        $this->assertTrue($doesntExist);
    }

    // =========================================================================
    // UPDATE TESTS
    // =========================================================================

    public function testUpdate(): void
    {
        $this->seedProducts();

        $affected = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->update([
                'product_name' => 'Updated via QueryBuilder',
                'product_price' => 99.99,
            ]);

        $this->assertEquals(1, $affected);
        $this->assertRowExists('product', [
            'product_id' => 1,
            'product_name' => 'Updated via QueryBuilder',
        ]);
    }

    public function testUpdateMultipleRows(): void
    {
        $this->seedProducts();

        $affected = $this->builder
            ->table('product')
            ->where('product_category_id', 1)
            ->update([
                'product_price' => 50.00,
            ]);

        $this->assertEquals(2, $affected);
    }

    // =========================================================================
    // DELETE TESTS
    // =========================================================================

    public function testDelete(): void
    {
        $this->seedProducts();

        $affected = $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->delete();

        $this->assertEquals(1, $affected);
        $this->assertRowNotExists('product', ['product_id' => 1]);
    }

    public function testDeleteMultipleRows(): void
    {
        $this->seedProducts();

        $affected = $this->builder
            ->table('product')
            ->where('product_stock', 0)
            ->delete();

        $this->assertEquals(1, $affected);
        $this->assertEquals(2, $this->getTableCount('product'));
    }

    // =========================================================================
    // INSERT TESTS
    // =========================================================================

    public function testInsert(): void
    {
        $this->seedCategories();

        $result = $this->builder
            ->table('product')
            ->insert([
                'product_uuid' => 'new-uuid-12345',
                'product_name' => 'New Product',
                'product_price' => 49.99,
                'product_stock' => 25,
                'product_category_id' => 1,
            ]);

        $this->assertTrue($result);
        $this->assertRowExists('product', ['product_name' => 'New Product']);
    }

    // =========================================================================
    // UTILITY TESTS
    // =========================================================================

    public function testReset(): void
    {
        $this->seedProducts();

        // Build a query
        $this->builder
            ->table('product')
            ->where('product_id', 1)
            ->orderBy('product_name');

        // Reset and build a different query
        $results = $this->builder
            ->reset()
            ->table('product')
            ->get();

        $this->assertCount(3, $results);
    }

    // =========================================================================
    // COMPLEX QUERY TESTS
    // =========================================================================

    public function testChainedQueries(): void
    {
        $this->seedProducts();

        // Complex query with multiple conditions
        $results = $this->builder
            ->table('product')
            ->select(['product_id', 'product_name', 'product_price'])
            ->where('product_active', 1)
            ->where('product_stock', '>', 0)
            ->whereBetween('product_price', 15.00, 35.00)
            ->orderBy('product_price', 'ASC')
            ->limit(10)
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('Test Product 1', $results[0]['product_name']);
        $this->assertEquals('Test Product 2', $results[1]['product_name']);
    }

    public function testRealWorldSearchWithFilters(): void
    {
        $this->seedProducts();

        $search = 'Product';
        $categoryId = 1;
        $minPrice = 15;
        $inStock = true;

        $results = $this->builder
            ->table('product')
            ->where('product_active', 1)
            ->when($search, function ($q) use ($search) {
                $q->whereLike('product_name', "%{$search}%");
            })
            ->when($categoryId, fn ($q, $id) => $q->where('product_category_id', $id))
            ->when($minPrice, fn ($q, $min) => $q->where('product_price', '>=', $min))
            ->when($inStock, fn ($q) => $q->where('product_stock', '>', 0))
            ->orderByAsc('product_name')
            ->get();

        $this->assertCount(2, $results);
    }
}
