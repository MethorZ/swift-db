<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Examples\Repository;

use MethorZ\SwiftDb\Examples\Entity\Product;
use MethorZ\SwiftDb\Repository\AbstractRepository;

/**
 * Example Product repository demonstrating all package features
 *
 * Shows Laravel-style query builder syntax introduced in v1.1
 *
 * @extends AbstractRepository<Product>
 */
class ProductRepository extends AbstractRepository
{
    /**
     * Get the table name
     */
    public function getTableName(): string
    {
        return 'product';
    }

    /**
     * Get the entity class
     *
     * @return class-string<Product>
     */
    public function getEntityClass(): string
    {
        return Product::class;
    }

    // =========================================================================
    // BASIC QUERIES (Laravel-style - implicit '=' operator)
    // =========================================================================

    /**
     * Find active products
     *
     * Demonstrates: Implicit '=' operator (2 args)
     *
     * @return array<Product>
     */
    public function findActive(): array
    {
        $rows = $this->query()
            ->where('product_active', 1)  // Implicit '='
            ->orderByDesc('product_created')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Find products by category
     *
     * Demonstrates: Array where syntax
     *
     * @return array<Product>
     */
    public function findByCategory(int $categoryId): array
    {
        $rows = $this->query()
            ->where([
                'product_category_id' => $categoryId,
                'product_active' => 1,
            ])
            ->orderByAsc('product_name')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Find products in price range
     *
     * @return array<Product>
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->whereBetween('product_price', $minPrice, $maxPrice)
            ->orderByAsc('product_price')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Find products with low stock
     *
     * Demonstrates: whereBetween() for range queries
     *
     * @return array<Product>
     */
    public function findLowStock(int $threshold = 10): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->whereBetween('product_stock', 1, $threshold)
            ->orderByAsc('product_stock')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    // =========================================================================
    // CONDITIONAL QUERIES (when/unless)
    // =========================================================================

    /**
     * Search products with optional filters
     *
     * Demonstrates: when() conditional builder
     *
     * @param array{
     *     search?: string,
     *     category_id?: int,
     *     min_price?: float,
     *     max_price?: float,
     *     in_stock?: bool
     * } $filters
     * @return array<Product>
     */
    public function searchWithFilters(array $filters): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->when($filters['search'] ?? null, function ($q, mixed $search) {
                $searchTerm = is_string($search) ? $search : '';
                $q->where(function ($q2) use ($searchTerm) {
                    $q2->whereLike('product_name', "%{$searchTerm}%")
                       ->orWhereLike('product_sku', "%{$searchTerm}%");
                });
            })
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                $q->where('product_category_id', $categoryId);
            })
            ->when($filters['min_price'] ?? null, function ($q, $minPrice) {
                $q->where('product_price', '>=', $minPrice);
            })
            ->when($filters['max_price'] ?? null, function ($q, $maxPrice) {
                $q->where('product_price', '<=', $maxPrice);
            })
            ->when($filters['in_stock'] ?? false, function ($q) {
                $q->where('product_stock', '>', 0);
            })
            ->orderByAsc('product_name')
            ->limit(50)
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    // =========================================================================
    // NESTED CONDITIONS (AND with OR inside)
    // =========================================================================

    /**
     * Find featured or discounted products
     *
     * Demonstrates: Nested conditions with closure
     * SQL: WHERE active = 1 AND (featured = 1 OR price < 10)
     *
     * @return array<Product>
     */
    public function findFeaturedOrCheap(): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->where(function ($q) {
                $q->where('product_featured', 1)
                  ->orWhere('product_price', '<', 10);
            })
            ->orderByDesc('product_featured')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Complex nested conditions
     *
     * SQL: WHERE active = 1 AND (
     *   (category_id = 5 AND price > 100)
     *   OR
     *   (featured = 1 AND stock > 0)
     * )
     *
     * @return array<Product>
     */
    public function findPremiumOrFeaturedInStock(int $categoryId): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->where(function ($q) use ($categoryId) {
                $q->where(function ($q2) use ($categoryId) {
                    $q2->where('product_category_id', $categoryId)
                       ->where('product_price', '>', 100);
                })
                ->orWhere(function ($q2) {
                    $q2->where('product_featured', 1)
                       ->where('product_stock', '>', 0);
                });
            })
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    // =========================================================================
    // JOINS (with closure conditions)
    // =========================================================================

    /**
     * Find products with category info
     *
     * Demonstrates: Simple join with implicit '='
     *
     * @return array<array<string, mixed>>
     */
    public function findWithCategory(): array
    {
        return $this->query()
            ->select([
                'product.*',
                'category.category_name',
            ])
            ->join('category', 'product.product_category_id', 'category.category_id')
            ->where('product.product_active', 1)
            ->get();
    }

    /**
     * Find products with active discounts
     *
     * Demonstrates: Join with closure for complex conditions
     *
     * @return array<array<string, mixed>>
     */
    public function findWithActiveDiscounts(): array
    {
        $today = date('Y-m-d');

        return $this->query()
            ->select([
                'product.*',
                'discount.discount_amount',
                'discount.discount_percentage',
            ])
            ->leftJoin('discount', function ($join) use ($today) {
                $join->on('product.product_id', 'discount.discount_product_id')
                     ->where('discount.discount_active', 1)
                     ->whereRaw('? BETWEEN discount.discount_start AND discount.discount_end', [$today]);
            })
            ->where('product.product_active', 1)
            ->get();
    }

    // =========================================================================
    // SUBQUERIES
    // =========================================================================

    /**
     * Find products in active categories
     *
     * Demonstrates: whereIn with subquery
     *
     * @return array<Product>
     */
    public function findInActiveCategories(): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->whereIn('product_category_id', function ($subQuery) {
                $subQuery
                    ->table('category')
                    ->select('category_id')
                    ->where('category_active', 1);
            })
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Find products with inventory
     *
     * Demonstrates: whereExists subquery
     *
     * @return array<Product>
     */
    public function findWithInventory(): array
    {
        $rows = $this->query()
            ->where('product_active', 1)
            ->whereExists(function ($subQuery) {
                $subQuery
                    ->table('inventory')
                    ->select('1')
                    ->whereColumn('inventory.inventory_product_id', 'product.product_id')
                    ->where('inventory.inventory_quantity', '>', 0);
            })
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * Update stock for multiple products (bulk operation)
     *
     * @param array<int, int> $stockUpdates [productId => newStock]
     */
    public function bulkUpdateStock(array $stockUpdates): int
    {
        if (empty($stockUpdates)) {
            return 0;
        }

        $products = $this->findMany(array_keys($stockUpdates));

        $bulk = $this->bulkUpsert()
            ->onDuplicateKeyUpdate(['product_stock', 'product_updated'])
            ->touchUpdatedOnDuplicate('product_updated');

        foreach ($products as $product) {
            if (isset($stockUpdates[$product->id])) {
                $product->stock = $stockUpdates[$product->id];
                $product->touchUpdated();
                $bulk->add($product);
            }
        }

        return $bulk->flush();
    }

    /**
     * Import products from external source (bulk insert)
     *
     * @param array<array{name: string, description?: string, price: float, stock: int, category_id?: int}> $products
     */
    public function importProducts(array $products): int
    {
        if (empty($products)) {
            return 0;
        }

        $bulk = $this->bulkInsert()->ignore(); // Ignore duplicates

        foreach ($products as $productData) {
            $product = $this->create();
            $product->name = $productData['name'];
            $product->description = $productData['description'] ?? null;
            $product->price = $productData['price'];
            $product->stock = $productData['stock'];
            $product->categoryId = $productData['category_id'] ?? null;
            $product->touchTimestamps();
            $product->generateUuid();

            $bulk->add($product);
        }

        return $bulk->flush();
    }

    // =========================================================================
    // AGGREGATES
    // =========================================================================

    /**
     * Count products by category
     *
     * @return array<int, int> [categoryId => count]
     */
    public function countByCategory(): array
    {
        $rows = $this->query()
            ->select(['product_category_id', 'COUNT(*) as count'])
            ->where('product_active', 1)
            ->whereNotNull('product_category_id')
            ->groupBy('product_category_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $categoryId = $row['product_category_id'] ?? null;
            $count = $row['count'] ?? 0;
            if ($categoryId !== null && is_numeric($categoryId) && is_numeric($count)) {
                $result[(int) $categoryId] = (int) $count;
            }
        }

        return $result;
    }

    /**
     * Deactivate products with zero stock
     */
    public function deactivateOutOfStock(): int
    {
        return $this->query()
            ->where([
                'product_stock' => 0,
                'product_active' => 1,
            ])
            ->update([
                'product_active' => 0,
                'product_updated' => date('Y-m-d H:i:s'),
            ]);
    }
}
