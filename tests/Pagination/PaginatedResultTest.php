<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Pagination;

use ArrayIterator;
use MethorZ\SwiftDb\Pagination\PaginatedResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PaginatedResult
 */
final class PaginatedResultTest extends TestCase
{
    // =========================================================================
    // CONSTRUCTION TESTS
    // =========================================================================

    public function testConstructorSetsProperties(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];

        $result = new PaginatedResult($items, 100, 15, 1);

        $this->assertEquals($items, $result->items);
        $this->assertEquals(100, $result->total);
        $this->assertEquals(15, $result->perPage);
        $this->assertEquals(1, $result->currentPage);
    }

    public function testConstructorWithEmptyItems(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertEmpty($result->items);
        $this->assertEquals(0, $result->total);
    }

    public function testConstructorWithLargeDataset(): void
    {
        $items = array_map(fn ($i) => ['id' => $i], range(1, 100));

        $result = new PaginatedResult($items, 10000, 100, 50);

        $this->assertCount(100, $result->items);
        $this->assertEquals(10000, $result->total);
        $this->assertEquals(100, $result->perPage);
        $this->assertEquals(50, $result->currentPage);
    }

    // =========================================================================
    // SPL INTERFACE TESTS
    // =========================================================================

    public function testCountReturnsTotal(): void
    {
        $result = new PaginatedResult([['id' => 1]], 250, 15, 1);

        $this->assertEquals(250, count($result));
        $this->assertEquals(250, $result->count());
    }

    public function testCountReturnsTotalNotItemCount(): void
    {
        $items = array_map(fn ($i) => ['id' => $i], range(1, 10));
        $result = new PaginatedResult($items, 100, 10, 1);

        // count() returns total (100), not item count (10)
        $this->assertEquals(100, count($result));
        $this->assertNotEquals(10, count($result));
    }

    public function testItemCountReturnsCurrentPageCount(): void
    {
        $items = array_map(fn ($i) => ['id' => $i], range(1, 10));
        $result = new PaginatedResult($items, 100, 10, 1);

        $this->assertEquals(10, $result->itemCount());
    }

    public function testItemCountWithPartialPage(): void
    {
        $items = [['id' => 96], ['id' => 97], ['id' => 98]];
        $result = new PaginatedResult($items, 98, 10, 10);

        // Last page with only 3 items
        $this->assertEquals(3, $result->itemCount());
    }

    public function testItemCountWithEmptyResult(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertEquals(0, $result->itemCount());
    }

    public function testCountVsItemCountSemantics(): void
    {
        $items = array_map(fn ($i) => ['id' => $i], range(1, 15));
        $result = new PaginatedResult($items, 500, 15, 1);

        // count() = total records across all pages
        $this->assertEquals(500, count($result));

        // itemCount() = items on current page
        $this->assertEquals(15, $result->itemCount());

        // iterator_count() = same as itemCount()
        $this->assertEquals(15, iterator_count($result));
    }

    public function testGetIteratorReturnsArrayIterator(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $result = new PaginatedResult($items, 100, 15, 1);

        $iterator = $result->getIterator();

        $this->assertInstanceOf(ArrayIterator::class, $iterator);
    }

    public function testIteratorContainsItems(): void
    {
        $items = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ];
        $result = new PaginatedResult($items, 100, 15, 1);

        $collected = [];
        foreach ($result as $item) {
            $collected[] = $item;
        }

        $this->assertEquals($items, $collected);
    }

    // =========================================================================
    // PAGINATION CALCULATION TESTS
    // =========================================================================

    public function testLastPageCalculation(): void
    {
        $result = new PaginatedResult([], 100, 15, 1);

        // ceil(100 / 15) = 7
        $this->assertEquals(7, $result->lastPage());
    }

    public function testLastPageWithExactDivision(): void
    {
        $result = new PaginatedResult([], 100, 10, 1);

        // ceil(100 / 10) = 10
        $this->assertEquals(10, $result->lastPage());
    }

    public function testLastPageWithRemainder(): void
    {
        $result = new PaginatedResult([], 101, 10, 1);

        // ceil(101 / 10) = 11
        $this->assertEquals(11, $result->lastPage());
    }

    public function testLastPageWithZeroTotal(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertEquals(1, $result->lastPage());
    }

    public function testLastPageWithSingleItem(): void
    {
        $result = new PaginatedResult([['id' => 1]], 1, 15, 1);

        $this->assertEquals(1, $result->lastPage());
    }

    public function testHasMorePagesWhenNotOnLastPage(): void
    {
        $result = new PaginatedResult([], 100, 15, 1);

        $this->assertTrue($result->hasMorePages());
    }

    public function testHasMorePagesWhenOnLastPage(): void
    {
        $result = new PaginatedResult([], 100, 15, 7);

        $this->assertFalse($result->hasMorePages());
    }

    public function testHasMorePagesWhenOnlyOnePage(): void
    {
        $result = new PaginatedResult([['id' => 1]], 5, 15, 1);

        $this->assertFalse($result->hasMorePages());
    }

    public function testHasPreviousPageWhenOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 15, 1);

        $this->assertFalse($result->hasPreviousPage());
    }

    public function testHasPreviousPageWhenNotOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 15, 2);

        $this->assertTrue($result->hasPreviousPage());
    }

    public function testHasPreviousPageWhenOnLastPage(): void
    {
        $result = new PaginatedResult([], 100, 15, 7);

        $this->assertTrue($result->hasPreviousPage());
    }

    // =========================================================================
    // HELPER METHOD TESTS
    // =========================================================================

    public function testIsEmptyWithNoItems(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertTrue($result->isEmpty());
    }

    public function testIsEmptyWithItems(): void
    {
        $result = new PaginatedResult([['id' => 1]], 100, 15, 1);

        $this->assertFalse($result->isEmpty());
    }

    public function testIsNotEmptyWithItems(): void
    {
        $result = new PaginatedResult([['id' => 1]], 100, 15, 1);

        $this->assertTrue($result->isNotEmpty());
    }

    public function testIsNotEmptyWithNoItems(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertFalse($result->isNotEmpty());
    }

    public function testFirstItemOnFirstPage(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $result = new PaginatedResult($items, 100, 15, 1);

        // First page, first item = 1
        $this->assertEquals(1, $result->firstItem());
    }

    public function testFirstItemOnSecondPage(): void
    {
        $items = [['id' => 16], ['id' => 17]];
        $result = new PaginatedResult($items, 100, 15, 2);

        // Second page with 15 per page, first item = 16
        $this->assertEquals(16, $result->firstItem());
    }

    public function testFirstItemOnThirdPage(): void
    {
        $items = [['id' => 21]];
        $result = new PaginatedResult($items, 100, 10, 3);

        // Third page with 10 per page, first item = 21
        $this->assertEquals(21, $result->firstItem());
    }

    public function testFirstItemWithEmptyResult(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertNull($result->firstItem());
    }

    public function testLastItemOnFirstPage(): void
    {
        $items = array_map(fn ($i) => ['id' => $i], range(1, 15));
        $result = new PaginatedResult($items, 100, 15, 1);

        // First page with 15 items, last item = 15
        $this->assertEquals(15, $result->lastItem());
    }

    public function testLastItemOnLastPartialPage(): void
    {
        $items = [['id' => 96], ['id' => 97], ['id' => 98], ['id' => 99], ['id' => 100]];
        $result = new PaginatedResult($items, 100, 15, 7);

        // Page 7 with 15 per page: first = 91, last = 91 + 5 - 1 = 95
        // Wait, let's recalculate: page 7, perPage 15
        // firstItem = (7-1) * 15 + 1 = 91
        // lastItem = 91 + count(items) - 1 = 91 + 5 - 1 = 95
        $this->assertEquals(95, $result->lastItem());
    }

    public function testLastItemWithSingleItem(): void
    {
        $items = [['id' => 1]];
        $result = new PaginatedResult($items, 1, 15, 1);

        $this->assertEquals(1, $result->lastItem());
    }

    public function testLastItemWithEmptyResult(): void
    {
        $result = new PaginatedResult([], 0, 15, 1);

        $this->assertNull($result->lastItem());
    }
}
