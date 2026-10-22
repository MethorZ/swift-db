# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-10-22

### Changed
- PHP requirement raised to `~8.4.27 || ~8.5.12` (releases patched for php2shell: CVE-2026-109618, CVE-2026-109619). PHP 8.3 is no longer supported.
- Updated dependencies: ramsey/uuid ^4.9; development: phpunit/phpunit ^13.4, phpstan/phpstan ^2.3, slevomat/coding-standard ^8.31, squizlabs/php_codesniffer ^4.0.1.
- `ConnectionConfig` default options use `Pdo\Mysql::ATTR_FOUND_ROWS` instead of `PDO::MYSQL_ATTR_FOUND_ROWS`, which PHP 8.5 deprecates (same value, no behaviour change). SECURITY.md examples use `Pdo\Mysql::ATTR_SSL_*`.
- Integration-test services use `mysql:8.4` (LTS) and `php:8.5.12-cli-trixie`; removed the `--default-authentication-plugin` flag that MySQL 8.4 no longer accepts.
- `AbstractEntity::getDirtyFields()` compares extracted values strictly instead of by string cast, so changes such as `null` to `''` or `0` to `null` are now detected as dirty.

## [1.2.0] - 2026-02-19

### Added
- **Connection convenience methods** with automatic reconnection support:
  - `prepare(string $sql): PDOStatement` - Prepare statement with reconnect
  - `query(string $sql): PDOStatement` - Execute raw query with reconnect
  - `execute(string $sql, array $params): int` - Execute and return affected rows
  - `fetchOne(string $sql, array $params): ?array` - Fetch single row or null
  - `fetchAll(string $sql, array $params): array` - Fetch all matching rows
- **Pagination support** with `paginate()` method on QueryBuilder and AbstractRepository
  - Returns `PaginatedResult` value object implementing `Countable` and `IteratorAggregate`
  - Supports all query conditions (where, orderBy, join, etc.)
  - Helper methods: `lastPage()`, `hasMorePages()`, `hasPreviousPage()`, `isEmpty()`, `firstItem()`, `lastItem()`
- Complete set of `or*` convenience methods for QueryBuilder:
  - `orWhereLike()` - OR WHERE LIKE for cleaner search queries
  - `orWhereBetween()` and `orWhereNotBetween()` - OR BETWEEN/NOT BETWEEN
  - `orWhereIn()` and `orWhereNotIn()` - OR IN/NOT IN (with subquery support)
  - `orWhereExists()` and `orWhereNotExists()` - OR EXISTS/NOT EXISTS
  - `orWhereRaw()` - OR raw WHERE clause
- **Documentation**: Comprehensive practical examples for all OR convenience methods in README
  - Multi-field search with `orWhereLike()`
  - Range queries with `orWhereBetween()`/`orWhereNotBetween()`
  - Set operations with `orWhereIn()`/`orWhereNotIn()` (including subquery examples)
  - Existence checks with `orWhereExists()`/`orWhereNotExists()`
  - Complex business logic with `orWhereRaw()`
- **Documentation**: Performance tip about using convenience methods for cleaner and more efficient SQL

### Changed
- `BulkInsert::execute()` now uses `Connection::prepare()` instead of `getPdo()->prepare()` for reconnect support
- Example `ProductRepository::findAdvanced()` now uses `orWhereLike()` for cleaner, more idiomatic syntax

### Removed
- Unused `$maxQuerySize` property from `BulkInsert`

### Fixed
- **README**: `BulkInsert` API table now correctly documents `add(array|EntityInterface)` instead of separate `add()` + `addRow()` methods
- **README**: Removed non-existent `setBatchSize()` from BulkInsert API table (batch size is constructor parameter)
- **README**: Added missing `addMany()` and `getPendingCount()` to BulkInsert API table
- **README**: Added missing `updateColumn()` and `incrementOnDuplicate()` to BulkUpsert API table
- **README**: Added missing `findAll()` and `paginate()` to AbstractRepository API table
- **README**: Added Connection API reference section

## [1.1.0] - 2024-12-08

### Added

- **Laravel-style QueryBuilder API**
  - Implicit equality operator: `->where('column', $value)`
  - Array syntax: `->where(['col1' => $val1, 'col2' => $val2])`
  - Nested conditions with closures: `->where(function ($q) { ... })`
  - `whereColumn()` for column-to-column comparisons
  - `whereExists()` and `whereNotExists()` for subqueries
  - `whereIn()` with subquery closure support
  - `when()` and `unless()` for conditional building
  - `tap()` for side effects without modifying the builder
  - `orderByAsc()` and `orderByDesc()` convenience methods
  - `take()` and `skip()` aliases for `limit()` and `offset()`
  - `union()` and `unionAll()` for combining queries
  - `doesntExist()` as inverse of `exists()`
  - `clone()` and `newQuery()` for query manipulation
- **JoinClause** helper class for complex join conditions
  - `on()`, `orOn()` for column comparisons
  - `where()`, `orWhere()` for value conditions in joins
  - `whereNull()`, `whereNotNull()` for NULL checks in joins
- **Comprehensive test coverage**
  - 246 unit tests covering all features
  - Integration tests for master/slave routing
  - Trait tests for Timestamps and UUID

### Changed

- QueryBuilder now uses Laravel-style API as primary interface
- Improved type safety throughout codebase

## [1.0.0] - 2024-12-08

### Added

- **Entity Pattern** with dirty tracking for efficient updates
- **Repository Pattern** with fluent query builder
- **Bulk Operations**
  - Multi-row INSERT with automatic batching
  - INSERT...ON DUPLICATE KEY UPDATE (upsert)
  - INSERT IGNORE support
- **Connection Management**
  - Master/slave routing
  - Automatic reconnection on connection loss
  - Lazy connection initialization
- **Optimistic Locking** via VersionTrait
- **Identity Map** (optional) for object identity within request
- **Deadlock Handling** with automatic retry and exponential backoff
- **Query Logging** for debugging and monitoring
- **Traits**
  - TimestampsTrait for created_at/updated_at
  - UuidTrait for UUID generation
  - VersionTrait for optimistic locking
- **Mapping Cache** for reflection caching in production

### Notes

- Requires PHP 8.3 or 8.4
- MySQL 8.0+ optimized
- Zero ORM overhead - direct PDO execution

