<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Query;

use MethorZ\SwiftDb\Query\JoinClause;
use PHPUnit\Framework\TestCase;

final class JoinClauseTest extends TestCase
{
    // =========================================================================
    // ON CLAUSE TESTS
    // =========================================================================

    public function testOnClause(): void
    {
        $join = new JoinClause('INNER', 'categories');
        $join->on('products.category_id', 'categories.id');

        $conditions = $join->getConditions();

        $this->assertCount(1, $conditions);
        $this->assertEquals('products.category_id', $conditions[0]['first']);
        $this->assertEquals('=', $conditions[0]['operator']);
        $this->assertEquals('categories.id', $conditions[0]['second']);
        $this->assertTrue($conditions[0]['isColumn']);
    }

    public function testOnClauseWithOperator(): void
    {
        $join = new JoinClause('LEFT', 'inventory');
        $join->on('products.id', '!=', 'inventory.product_id');

        $conditions = $join->getConditions();

        $this->assertEquals('!=', $conditions[0]['operator']);
    }

    public function testOnClauseWithLessThan(): void
    {
        $join = new JoinClause('INNER', 'price_history');
        $join->on('products.price', '<', 'price_history.max_price');

        $conditions = $join->getConditions();

        $this->assertEquals('<', $conditions[0]['operator']);
    }

    public function testMultipleOnClauses(): void
    {
        $join = new JoinClause('INNER', 'order_item');
        $join->on('products.id', 'order_item.product_id')
             ->on('products.variant_id', 'order_item.variant_id');

        $conditions = $join->getConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals('AND', $conditions[0]['type']);
        $this->assertEquals('AND', $conditions[1]['type']);
    }

    // =========================================================================
    // OR ON CLAUSE TESTS
    // =========================================================================

    public function testOrOnClause(): void
    {
        $join = new JoinClause('INNER', 'categories');
        $join->on('products.category_id', 'categories.id')
             ->orOn('products.alt_category_id', 'categories.id');

        $conditions = $join->getConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals('AND', $conditions[0]['type']);
        $this->assertEquals('OR', $conditions[1]['type']);
    }

    public function testOrOnClauseWithOperator(): void
    {
        $join = new JoinClause('LEFT', 'related_products');
        $join->on('products.id', 'related_products.product_id')
             ->orOn('products.id', '=', 'related_products.related_id');

        $conditions = $join->getConditions();

        $this->assertEquals('OR', $conditions[1]['type']);
        $this->assertEquals('=', $conditions[1]['operator']);
    }

    // =========================================================================
    // WHERE CLAUSE TESTS
    // =========================================================================

    public function testWhereClause(): void
    {
        $join = new JoinClause('INNER', 'categories');
        $join->on('products.category_id', 'categories.id')
             ->where('categories.active', true);

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(2, $conditions);
        $this->assertFalse($conditions[1]['isColumn']);
        $this->assertEquals([true], $bindings);
    }

    public function testWhereClauseWithOperator(): void
    {
        $join = new JoinClause('LEFT', 'discounts');
        $join->on('products.id', 'discounts.product_id')
             ->where('discounts.amount', '>', 10);

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertEquals('>', $conditions[1]['operator']);
        $this->assertEquals([10], $bindings);
    }

    public function testWhereClauseWithLessThanOrEqual(): void
    {
        $join = new JoinClause('LEFT', 'discounts');
        $join->on('products.id', 'discounts.product_id')
             ->where('discounts.percentage', '<=', 50);

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertEquals('<=', $conditions[1]['operator']);
        $this->assertEquals([50], $bindings);
    }

    public function testMultipleWhereClauses(): void
    {
        $join = new JoinClause('LEFT', 'discounts');
        $join->on('products.id', 'discounts.product_id')
             ->where('discounts.active', true)
             ->where('discounts.percentage', '>', 0)
             ->where('discounts.type', 'percentage');

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(4, $conditions);
        $this->assertEquals([true, 0, 'percentage'], $bindings);
    }

    // =========================================================================
    // OR WHERE CLAUSE TESTS
    // =========================================================================

    public function testOrWhereClause(): void
    {
        $join = new JoinClause('LEFT', 'categories');
        $join->on('products.category_id', 'categories.id')
             ->where('categories.active', true)
             ->orWhere('categories.featured', true);

        $conditions = $join->getConditions();

        $this->assertEquals('OR', $conditions[2]['type']);
    }

    public function testOrWhereClauseWithOperator(): void
    {
        $join = new JoinClause('LEFT', 'discounts');
        $join->on('products.id', 'discounts.product_id')
             ->where('discounts.percentage', '>', 10)
             ->orWhere('discounts.fixed_amount', '>', 5);

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertEquals('OR', $conditions[2]['type']);
        $this->assertEquals('>', $conditions[2]['operator']);
        $this->assertEquals([10, 5], $bindings);
    }

    // =========================================================================
    // WHERE NULL TESTS
    // =========================================================================

    public function testWhereNull(): void
    {
        $join = new JoinClause('LEFT', 'categories');
        $join->on('products.category_id', 'categories.id')
             ->whereNull('categories.deleted_at');

        $conditions = $join->getConditions();

        $this->assertEquals('IS NULL', $conditions[1]['operator']);
        $this->assertFalse($conditions[1]['isColumn']);
    }

    public function testWhereNotNull(): void
    {
        $join = new JoinClause('INNER', 'categories');
        $join->on('products.category_id', 'categories.id')
             ->whereNotNull('categories.parent_id');

        $conditions = $join->getConditions();

        $this->assertEquals('IS NOT NULL', $conditions[1]['operator']);
    }

    public function testWhereNullDoesNotAddToBindings(): void
    {
        $join = new JoinClause('LEFT', 'products');
        $join->on('orders.product_id', 'products.id')
             ->whereNull('products.deleted_at');

        $bindings = $join->getBindings();

        $this->assertEmpty($bindings);
    }

    // =========================================================================
    // TYPE AND TABLE GETTERS
    // =========================================================================

    public function testGetType(): void
    {
        $innerJoin = new JoinClause('INNER', 'table');
        $leftJoin = new JoinClause('LEFT', 'table');
        $rightJoin = new JoinClause('RIGHT', 'table');

        $this->assertEquals('INNER', $innerJoin->getType());
        $this->assertEquals('LEFT', $leftJoin->getType());
        $this->assertEquals('RIGHT', $rightJoin->getType());
    }

    public function testGetTable(): void
    {
        $join = new JoinClause('INNER', 'categories');

        $this->assertEquals('categories', $join->getTable());
    }

    public function testGetTableWithSchemaPrefix(): void
    {
        $join = new JoinClause('INNER', 'mydb.categories');

        $this->assertEquals('mydb.categories', $join->getTable());
    }

    // =========================================================================
    // COMPLEX CONDITION TESTS
    // =========================================================================

    public function testComplexJoinConditions(): void
    {
        $join = new JoinClause('LEFT', 'discounts');
        $join->on('products.id', 'discounts.product_id')
             ->where('discounts.active', true)
             ->where('discounts.amount', '>', 0)
             ->whereNull('discounts.deleted_at');

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(4, $conditions);
        $this->assertEquals([true, 0], $bindings);
    }

    public function testJoinWithDateConditions(): void
    {
        $today = '2024-01-15';

        $join = new JoinClause('LEFT', 'promotions');
        $join->on('products.id', 'promotions.product_id')
             ->where('promotions.start_date', '<=', $today)
             ->where('promotions.end_date', '>=', $today)
             ->where('promotions.active', true);

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(4, $conditions);
        $this->assertEquals([$today, $today, true], $bindings);
    }

    public function testJoinWithMixedConditions(): void
    {
        $join = new JoinClause('LEFT', 'inventory');
        $join->on('products.id', 'inventory.product_id')
             ->on('products.warehouse_id', 'inventory.warehouse_id')
             ->where('inventory.quantity', '>', 0)
             ->whereNull('inventory.reserved_until')
             ->orWhere('inventory.reserved_until', '<', '2024-01-01');

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(5, $conditions);
        // 2 ON conditions (column comparison, no bindings)
        // 1 WHERE (quantity > 0)
        // 1 WHERE NULL (no binding)
        // 1 OR WHERE (reserved_until < date)
        $this->assertEquals([0, '2024-01-01'], $bindings);
    }

    // =========================================================================
    // FLUENT INTERFACE TESTS
    // =========================================================================

    public function testFluentInterface(): void
    {
        $join = new JoinClause('INNER', 'categories');

        $result = $join->on('products.category_id', 'categories.id')
                       ->where('categories.active', true)
                       ->whereNull('categories.deleted_at')
                       ->whereNotNull('categories.name');

        $this->assertSame($join, $result);
        $this->assertCount(4, $join->getConditions());
    }

    public function testAllMethodsReturnSelf(): void
    {
        $join = new JoinClause('LEFT', 'test');

        $this->assertSame($join, $join->on('a', 'b'));
        $this->assertSame($join, $join->orOn('c', 'd'));
        $this->assertSame($join, $join->where('e', 1));
        $this->assertSame($join, $join->orWhere('f', 2));
        $this->assertSame($join, $join->whereNull('g'));
        $this->assertSame($join, $join->whereNotNull('h'));
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testEmptyJoinClause(): void
    {
        $join = new JoinClause('INNER', 'categories');

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertEmpty($conditions);
        $this->assertEmpty($bindings);
    }

    public function testOnlyWhereConditions(): void
    {
        // While unusual, it should be possible
        $join = new JoinClause('INNER', 'config');
        $join->where('config.key', 'tax_rate');

        $conditions = $join->getConditions();
        $bindings = $join->getBindings();

        $this->assertCount(1, $conditions);
        $this->assertEquals(['tax_rate'], $bindings);
    }

    public function testBindingsOrderIsCorrect(): void
    {
        $join = new JoinClause('LEFT', 'test');
        $join->on('a.id', 'b.id')
             ->where('b.value1', 'first')
             ->where('b.value2', 'second')
             ->orWhere('b.value3', 'third');

        $bindings = $join->getBindings();

        $this->assertEquals(['first', 'second', 'third'], $bindings);
    }
}
