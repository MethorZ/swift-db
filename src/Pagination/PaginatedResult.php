<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Paginated result set with metadata
 *
 * Implements SPL interfaces for convenient iteration and counting:
 * - count($result) returns total record count
 * - foreach ($result as $row) iterates over items
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final readonly class PaginatedResult implements Countable, IteratorAggregate
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {
    }

    /**
     * Get the total number of records (Countable interface)
     *
     * @return int<0, max>
     */
    public function count(): int
    {
        return max(0, $this->total);
    }

    /**
     * Get an iterator for the items (IteratorAggregate interface)
     *
     * @return ArrayIterator<int, array<string, mixed>>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get the last page number
     */
    public function lastPage(): int
    {
        if ($this->total === 0 || $this->perPage === 0) {
            return 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Check if there are more pages after the current page
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Check if there is a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Check if the result set is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if the result set is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the index of the first item on this page (1-based)
     *
     * Returns null if the result set is empty
     */
    public function firstItem(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Get the index of the last item on this page (1-based)
     *
     * Returns null if the result set is empty
     */
    public function lastItem(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }

        $firstItem = $this->firstItem();

        if ($firstItem === null) {
            return null;
        }

        return $firstItem + count($this->items) - 1;
    }
}
