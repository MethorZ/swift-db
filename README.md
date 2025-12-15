# MethorZ SwiftDb

[![CI](https://github.com/methorz/swift-db/actions/workflows/ci.yml/badge.svg)](https://github.com/methorz/swift-db/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/methorz/swift-db/branch/main/graph/badge.svg)](https://codecov.io/gh/methorz/swift-db)

High-performance MySQL database layer with bulk operations, built on PDO.

## Features

- **High Performance**: Direct PDO with no abstraction overhead
- **Bulk Operations**: Multi-row INSERT, INSERT...ON DUPLICATE KEY UPDATE
- **Entity Pattern**: Clean entity classes with dirty tracking
- **Repository Pattern**: Type-safe repositories with query builder
- **MySQL Optimized**: Designed specifically for MySQL
- **Deadlock Handling**: Automatic retry with exponential backoff
- **Connection Management**: Master/slave support, reconnection handling
- **Query Logging**: Built-in debugging and monitoring

## Installation

```bash
composer require methorz/swift-db
```

## Quick Start

### Configuration

```php
// config/autoload/database.global.php
return [
    'database' => [
        'connections' => [
            'default' => [
                'dsn' => 'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
                'username' => 'root',
                'password' => '',
            ],
            'replica' => [
                'dsn' => 'mysql:host=replica;dbname=myapp;charset=utf8mb4',
                'username' => 'readonly',
                'password' => '',
            ],
        ],
        'default' => 'default',
        'read_from' => 'replica',  // Optional: route reads to replica
        'write_to' => 'default',   // Optional: route writes to master
        'cache_dir' => 'data/cache/database',  // Optional: for reflection cache
    ],
];
```

### Define an Entity

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\TimestampsTrait;
use MethorZ\SwiftDb\Trait\UuidTrait;
use DateTimeImmutable;

class Product extends AbstractEntity
{
    use TimestampsTrait;
    use UuidTrait;

    public ?int $id = null;
    public string $name = '';
    public float $price = 0.0;
    public int $stock = 0;

    public static function getTableName(): string
    {
        return 'product';
    }

    public function getColumnMapping(): array
    {
        return [
            'id' => 'product_id',
            'name' => 'product_name',
            'price' => 'product_price',
            'stock' => 'product_stock',
            ...$this->getTimestampMapping(),
            ...$this->getUuidMapping(),
        ];
    }
}
```

### Define a Repository

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Product;
use MethorZ\SwiftDb\Repository\AbstractRepository;

/**
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
     * Find products by category with price range
     *
     * @return array<Product>
     */
    public function findByCategory(int $categoryId, float $minPrice, float $maxPrice): array
    {
        $rows = $this->query()
            ->where('product_category_id', $categoryId)
            ->whereBetween('product_price', $minPrice, $maxPrice)
            ->orderBy('product_price', 'ASC')
            ->get();

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }
}
```

### Basic Usage

```php
// Get repository from container
$repository = $container->get(ProductRepository::class);

// Create and save
$product = $repository->create();
$product->name = 'Widget';
$product->price = 29.99;
$product->stock = 100;
$repository->save($product);

// Find
$product = $repository->find(1);
$product = $repository->findOrFail(1);  // Throws if not found

// Update
$product->price = 24.99;
$repository->save($product);  // Only updates changed fields

// Delete
$repository->delete($product);
// or
$repository->deleteById(1);

// Query builder
$products = $repository->query()
    ->where('product_price', '>', 10)
    ->whereNotNull('product_stock')
    ->orderBy('product_created', 'DESC')
    ->limit(10)
    ->get();
```

### Bulk Operations

```php
// Bulk insert - fast!
$bulk = $repository->bulkInsert();
foreach ($products as $product) {
    $bulk->add($product);
}
$bulk->flush();  // Executes in batches of 1000

// Bulk upsert (INSERT ... ON DUPLICATE KEY UPDATE)
$bulk = $repository->bulkUpsert()
    ->onDuplicateKeyUpdate(['product_price', 'product_stock'])
    ->touchUpdatedOnDuplicate('product_updated');

foreach ($products as $product) {
    $bulk->add($product);
}
$bulk->flush();

// INSERT IGNORE
$bulk = $repository->bulkInsert()->ignore();
```

### Transactions

```php
// Manual
$repository->beginTransaction();
try {
    $repository->save($product1);
    $repository->save($product2);
    $repository->commit();
} catch (\Throwable $e) {
    $repository->rollback();
    throw $e;
}

// Or use the helper
$repository->transaction(function () use ($repository, $product1, $product2) {
    $repository->save($product1);
    $repository->save($product2);
});
```

### Optimistic Locking

```php
use MethorZ\SwiftDb\Trait\VersionTrait;

class Product extends AbstractEntity
{
    use VersionTrait;

    // ...

    public function getColumnMapping(): array
    {
        return [
            // ...
            ...$this->getVersionMapping(),
        ];
    }
}

// Now updates will check the version
try {
    $repository->save($product);
} catch (OptimisticLockException $e) {
    // Entity was modified by another process
}
```

## Performance Tips

1. **Use bulk operations** for multiple inserts/updates
2. **Use dirty tracking** - only changed fields are updated
3. **Batch your queries** - use `findMany()` instead of multiple `find()` calls
4. **Use the query builder** for complex queries instead of multiple simple queries
5. **Enable the mapping cache** in production for faster hydration
6. **Use convenience methods** - `whereBetween()` generates more efficient SQL than two separate `where()` calls, and `orWhereLike()` is clearer than `orWhere('col', 'LIKE', '?')`

## Development

### Quick Start

```bash
# Install dependencies
composer install

# Run unit tests
make test-unit
# or
vendor/bin/phpunit --testsuite=unit

# Run integration tests (requires Docker)
make test-integration

# Run all quality checks
make quality
```

### Docker Setup

The package includes a Docker setup for running integration tests with a real MySQL database.

```bash
# Start the database container
make start

# Run integration tests
make test-integration

# Stop containers
make stop

# View logs
make logs

# Access MySQL shell
make db-shell
```

### Test Structure

```
tests/
├── Unit tests (default, no database required)
│   ├── Entity/
│   ├── Query/
│   ├── Bulk/
│   ├── Connection/
│   ├── Cache/
│   └── Exception/
└── Integration/  (requires Docker database)
    ├── Repository/
    ├── Bulk/
    └── Query/
```

### Makefile Commands

| Command | Description |
|---------|-------------|
| `make start` | Start Docker containers |
| `make stop` | Stop Docker containers |
| `make test` | Run all tests |
| `make test-unit` | Run unit tests only |
| `make test-integration` | Run integration tests |
| `make quality` | Run CS fix, CS check, and PHPStan |
| `make cs-check` | Check code style |
| `make cs-fix` | Fix code style |
| `make analyze` | Run PHPStan |
| `make shell` | Open PHP shell |
| `make db-shell` | Open MySQL shell |

## API Reference

### AbstractEntity

Base class for all entities.

| Method | Description |
|--------|-------------|
| `getId(): mixed` | Get primary key value |
| `setId(mixed $id): void` | Set primary key value |
| `hydrate(array $data): void` | Populate entity from database row |
| `extract(): array` | Extract entity data for persistence |
| `isDirty(): bool` | Check if entity has unsaved changes |
| `getDirtyFields(): array` | Get only changed fields |
| `markPersisted(): void` | Mark entity as saved |
| `getColumnMapping(): array` | Define property-to-column mapping |
| `getPrimaryKeyColumn(): string` | Get primary key column name |

### AbstractRepository

Base class for all repositories.

| Method | Description |
|--------|-------------|
| `find(mixed $id): ?T` | Find by primary key |
| `findOrFail(mixed $id): T` | Find or throw exception |
| `findMany(array $ids): array` | Find multiple by IDs |
| `save(EntityInterface $entity): void` | Insert or update entity |
| `delete(EntityInterface $entity): bool` | Delete entity |
| `deleteById(mixed $id): bool` | Delete by primary key |
| `create(): T` | Create new entity instance |
| `count(): int` | Count all records |
| `query(): QueryBuilder` | Create query builder |
| `bulkInsert(): BulkInsert` | Create bulk insert operation |
| `bulkUpsert(): BulkUpsert` | Create bulk upsert operation |
| `beginTransaction(): void` | Start transaction |
| `commit(): void` | Commit transaction |
| `rollback(): void` | Rollback transaction |
| `transaction(callable $callback): mixed` | Execute in transaction |

### QueryBuilder

Fluent query builder for MySQL with Laravel-style syntax.

#### Basic Methods

| Method | Description |
|--------|-------------|
| `table(string $table): self` | Set table name |
| `from(string $table): self` | Alias for table() |
| `select(array\|string $columns): self` | Set columns to select |
| `addSelect(string ...$columns): self` | Add columns |
| `selectSub(Closure $callback, string $as): self` | Add subquery column |
| `selectRaw(string $expression): self` | Add raw select |
| `distinct(): self` | Select distinct rows |

#### WHERE Clauses (Laravel-style)

| Method | Description |
|--------|-------------|
| `where($column, $value)` | Implicit '=' operator |
| `where($column, $operator, $value)` | Explicit operator |
| `where(['col' => $val, ...])` | Array of conditions |
| `where(Closure $callback)` | Nested conditions |
| `orWhere(...)` | OR variant of where() |
| `whereColumn($first, $second)` | Compare two columns |
| `orWhereColumn(...)` | OR variant of whereColumn() |
| `whereIn($column, $values\|Closure)` | IN clause or subquery |
| `orWhereIn($column, $values\|Closure)` | OR WHERE IN |
| `whereNotIn($column, $values\|Closure)` | NOT IN clause |
| `orWhereNotIn($column, $values\|Closure)` | OR WHERE NOT IN |
| `whereNull($column)` | IS NULL |
| `orWhereNull($column)` | OR WHERE IS NULL |
| `whereNotNull($column)` | IS NOT NULL |
| `orWhereNotNull($column)` | OR WHERE IS NOT NULL |
| `whereBetween($column, $min, $max)` | BETWEEN |
| `orWhereBetween($column, $min, $max)` | OR WHERE BETWEEN |
| `whereNotBetween($column, $min, $max)` | NOT BETWEEN |
| `orWhereNotBetween($column, $min, $max)` | OR WHERE NOT BETWEEN |
| `whereLike($column, $pattern)` | LIKE pattern |
| `orWhereLike($column, $pattern)` | OR WHERE LIKE |
| `whereExists(Closure $callback)` | EXISTS subquery |
| `orWhereExists(Closure $callback)` | OR WHERE EXISTS |
| `whereNotExists(Closure $callback)` | NOT EXISTS |
| `orWhereNotExists(Closure $callback)` | OR WHERE NOT EXISTS |
| `whereRaw($sql, $bindings)` | Raw WHERE clause |
| `orWhereRaw($sql, $bindings)` | OR raw WHERE clause |

#### JOINs (with closure support)

| Method | Description |
|--------|-------------|
| `join($table, $first, $second)` | INNER JOIN (implicit '=') |
| `join($table, $first, $op, $second)` | INNER JOIN with operator |
| `join($table, Closure $callback)` | Complex join conditions |
| `leftJoin(...)` | LEFT JOIN variants |
| `rightJoin(...)` | RIGHT JOIN variants |

#### Ordering & Grouping

| Method | Description |
|--------|-------------|
| `orderBy($column, $direction)` | ORDER BY |
| `orderBy(['col' => 'dir', ...])` | Multiple columns |
| `orderByAsc($column)` | ORDER BY ASC |
| `orderByDesc($column)` | ORDER BY DESC |
| `groupBy($columns)` | GROUP BY |
| `limit($n)` / `take($n)` | Set LIMIT |
| `offset($n)` / `skip($n)` | Set OFFSET |

#### Unions

| Method | Description |
|--------|-------------|
| `union(QueryBuilder $query)` | UNION |
| `unionAll(QueryBuilder $query)` | UNION ALL |

#### Conditional Building

| Method | Description |
|--------|-------------|
| `when($condition, Closure $callback, ?Closure $default)` | Apply if truthy |
| `unless($condition, Closure $callback)` | Apply if falsy |
| `tap(Closure $callback)` | Execute side effect |

#### Execution

| Method | Description |
|--------|-------------|
| `get(): array` | Get all rows |
| `first(): ?array` | Get first row |
| `count(): int` | COUNT query |
| `exists(): bool` | Check existence |
| `doesntExist(): bool` | Check non-existence |
| `update(array $values): int` | UPDATE query |
| `delete(): int` | DELETE query |
| `insert(array $values): bool` | INSERT query |
| `toSql(): string` | Get SQL string |
| `getBindings(): array` | Get bindings |

#### Query Builder Examples

```php
// Implicit equals (Laravel-style)
$rows = $this->query()
    ->where('product_active', true)
    ->where('product_category_id', $categoryId)
    ->get();

// Array syntax
$rows = $this->query()
    ->where([
        'product_active' => true,
        'product_status' => 'published',
    ])
    ->get();

// Nested conditions (AND with OR inside)
// WHERE active = 1 AND (price < 10 OR featured = 1)
$rows = $this->query()
    ->where('product_active', true)
    ->where(function ($q) {
        $q->where('product_price', '<', 10)
          ->orWhere('product_featured', true);
    })
    ->get();

// Conditional building
$rows = $this->query()
    ->where('product_active', true)
    ->when($categoryId, fn($q, $id) => $q->where('product_category_id', $id))
    ->when($minPrice, fn($q, $min) => $q->where('product_price', '>=', $min))
    ->get();

// Join with conditions
$rows = $this->query()
    ->leftJoin('discount', function ($join) use ($today) {
        $join->on('product.product_id', 'discount.discount_product_id')
             ->where('discount.discount_active', true)
             ->where('discount.discount_start', '<=', $today);
    })
    ->get();

// Subquery in whereIn
$rows = $this->query()
    ->whereIn('product_category_id', function ($sub) {
        $sub->table('category')
            ->select('category_id')
            ->where('category_active', true);
    })
    ->get();

// EXISTS subquery
$rows = $this->query()
    ->whereExists(function ($sub) {
        $sub->table('inventory')
            ->select('1')
            ->whereColumn('inventory.product_id', 'product.product_id')
            ->where('inventory.quantity', '>', 0);
    })
    ->get();

// OR convenience methods - Multi-field search
// WHERE active = 1 AND (name LIKE ? OR sku LIKE ?)
$rows = $this->query()
    ->where('product_active', true)
    ->where(function ($q) use ($searchTerm) {
        $q->whereLike('product_name', "%{$searchTerm}%")
          ->orWhereLike('product_sku', "%{$searchTerm}%");
    })
    ->get();

// OR BETWEEN - Price range or high stock
// WHERE (price BETWEEN 10 AND 50) OR (stock BETWEEN 100 AND 500)
$rows = $this->query()
    ->whereBetween('product_price', 10.0, 50.0)
    ->orWhereBetween('product_stock', 100, 500)
    ->get();

// OR NOT BETWEEN - Exclude middle range
// WHERE price NOT BETWEEN 50 AND 100 OR stock NOT BETWEEN 10 AND 50
$rows = $this->query()
    ->whereNotBetween('product_price', 50.0, 100.0)
    ->orWhereNotBetween('product_stock', 10, 50)
    ->get();

// OR IN - Multiple category sets
// WHERE category_id IN (1, 2) OR status IN ('active', 'featured')
$rows = $this->query()
    ->whereIn('product_category_id', [1, 2])
    ->orWhereIn('product_status', ['active', 'featured'])
    ->get();

// OR NOT IN - Exclude multiple sets
// WHERE status NOT IN ('deleted', 'archived') OR category_id NOT IN (5, 6)
$rows = $this->query()
    ->whereNotIn('product_status', ['deleted', 'archived'])
    ->orWhereNotIn('product_category_id', [5, 6])
    ->get();

// OR IN with subquery - Active categories OR featured tags
$rows = $this->query()
    ->whereIn('product_category_id', function ($sub) {
        $sub->table('category')->select('category_id')->where('category_active', true);
    })
    ->orWhereIn('product_tag_id', function ($sub) {
        $sub->table('tag')->select('tag_id')->where('tag_featured', true);
    })
    ->get();

// OR EXISTS - Has inventory OR has pre-orders
$rows = $this->query()
    ->whereExists(function ($sub) {
        $sub->table('inventory')
            ->select('1')
            ->whereColumn('inventory.product_id', 'product.product_id')
            ->where('inventory.quantity', '>', 0);
    })
    ->orWhereExists(function ($sub) {
        $sub->table('pre_order')
            ->select('1')
            ->whereColumn('pre_order.product_id', 'product.product_id');
    })
    ->get();

// OR NOT EXISTS - No reviews OR no ratings
$rows = $this->query()
    ->whereNotExists(function ($sub) {
        $sub->table('review')->select('1')->whereColumn('review.product_id', 'product.product_id');
    })
    ->orWhereNotExists(function ($sub) {
        $sub->table('rating')->select('1')->whereColumn('rating.product_id', 'product.product_id');
    })
    ->get();

// OR RAW - Complex business logic
// WHERE margin > 20 OR (price * discount_multiplier) < cost
$rows = $this->query()
    ->whereRaw('(product_price - product_cost) / product_price > ?', [0.2])
    ->orWhereRaw('product_price * ? < product_cost', [0.8])
    ->get();
```

### BulkInsert

High-performance multi-row INSERT.

| Method | Description |
|--------|-------------|
| `add(EntityInterface $entity): self` | Add entity to batch |
| `addRow(array $row): self` | Add raw row to batch |
| `flush(): int` | Execute and return affected rows |
| `ignore(): self` | Use INSERT IGNORE |
| `setBatchSize(int $size): self` | Set batch size (default: 1000) |
| `getTotalAffected(): int` | Get total rows affected |

### BulkUpsert

INSERT ... ON DUPLICATE KEY UPDATE.

| Method | Description |
|--------|-------------|
| `onDuplicateKeyUpdate(array $columns): self` | Set columns to update |
| `touchUpdatedOnDuplicate(string $column): self` | Update timestamp on duplicate |
| *(inherits all BulkInsert methods)* | |

### IdentityMap (Optional)

Caches loaded entities to prevent duplicate instances.

| Method | Description |
|--------|-------------|
| `get(string $class, int\|string $id): ?T` | Get cached entity |
| `set(string $class, int\|string $id, EntityInterface $entity): void` | Cache entity |
| `has(string $class, int\|string $id): bool` | Check if cached |
| `remove(string $class, int\|string $id): void` | Remove from cache |
| `clear(?string $class): void` | Clear cache (all or by class) |
| `getStats(): array` | Get hit/miss statistics |

### JoinClause

Helper class for building complex JOIN conditions (used with closure joins).

| Method | Description |
|--------|-------------|
| `on($first, $second)` | Add ON condition (column = column) |
| `on($first, $operator, $second)` | Add ON condition with operator |
| `orOn($first, $second)` | Add OR ON condition |
| `where($column, $value)` | Add WHERE condition (column = value) |
| `where($column, $operator, $value)` | Add WHERE condition with operator |
| `orWhere(...)` | Add OR WHERE condition |
| `whereNull($column)` | Add WHERE IS NULL |
| `whereNotNull($column)` | Add WHERE IS NOT NULL |

**Example:**

```php
$this->query()
    ->leftJoin('discount', function (JoinClause $join) use ($today) {
        $join->on('product.product_id', 'discount.product_id')
             ->where('discount.active', true)
             ->where('discount.start_date', '<=', $today)
             ->whereNull('discount.deleted_at');
    })
    ->get();
```

### QueryLogger

Debug and monitor query execution.

| Method | Description |
|--------|-------------|
| `enable(): void` | Enable logging |
| `disable(): void` | Disable logging |
| `isEnabled(): bool` | Check if enabled |
| `log(string $sql, array $params, float $duration): void` | Log a query |
| `getQueries(): array` | Get all logged queries |
| `getQueryCount(): int` | Get total query count |
| `getTotalTime(): float` | Get total execution time (seconds) |
| `getSlowestQuery(): ?array` | Get the slowest query |
| `getSlowQueries(float $threshold): array` | Get queries slower than threshold |
| `getSummary(): array` | Get statistics summary |
| `clear(): void` | Clear logged queries |

**Example:**

```php
use MethorZ\SwiftDb\Query\QueryLogger;

// Create with PSR-3 logger (optional)
$logger = new QueryLogger($psrLogger);

// Pass to repository/query builder
$repository = new ProductRepository($connection, $logger);

// After some operations...
$summary = $logger->getSummary();
// ['count' => 5, 'total_time_ms' => 12.5, 'avg_time_ms' => 2.5, 'slowest_ms' => 5.2]

// Find slow queries (> 100ms)
$slowQueries = $logger->getSlowQueries(0.1);

// Clear for next request
$logger->clear();
```

### MappingCache

OPcache-friendly cache for entity column mappings (production optimization).

| Method | Description |
|--------|-------------|
| `getMapping(string $entityClass): array` | Get cached mapping for entity class |
| `clear(): void` | Clear all cached mappings |
| `clearFor(string $entityClass): void` | Clear mapping for specific class |

**Example:**

```php
use MethorZ\SwiftDb\Cache\MappingCache;

// Configure with cache directory
$cache = new MappingCache('data/cache/database');

// First call: builds mapping via reflection, stores as PHP file
$mapping = $cache->getMapping(Product::class);

// Subsequent calls: loads from OPcache (fast!)
$mapping = $cache->getMapping(Product::class);

// Clear cache after entity changes (e.g., in deployment)
$cache->clear();
```

### Traits

| Trait | Properties | Description |
|-------|------------|-------------|
| `TimestampsTrait` | `createdAt`, `updatedAt` | Auto-managed timestamps |
| `UuidTrait` | `uuid` | UUID v7 generation |
| `VersionTrait` | `version` | Optimistic locking |

### Exceptions

| Exception | HTTP Code | Description |
|-----------|-----------|-------------|
| `DatabaseException` | 500 | Base exception |
| `ConnectionException` | 500 | Connection failures |
| `QueryException` | 500 | Query execution errors |
| `EntityException` | 500 | Entity-related errors |
| `DeadlockException` | 500 | MySQL deadlock detected |
| `DuplicateEntryException` | 409 | Unique constraint violation |
| `OptimisticLockException` | 409 | Version mismatch |

## Requirements

- PHP 8.3 or 8.4
- PDO with MySQL driver
- MySQL 8.0+
- Docker (for integration tests)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines.

## License

MIT

