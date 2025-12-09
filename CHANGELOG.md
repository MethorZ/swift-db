# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
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
- Example `ProductRepository::findAdvanced()` now uses `orWhereLike()` for cleaner, more idiomatic syntax

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

