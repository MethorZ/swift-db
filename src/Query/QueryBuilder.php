<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Query;

use Closure;
use MethorZ\SwiftDb\Connection\Connection;
use PDO;
use PDOStatement;

/**
 * Fluent query builder for MySQL (Laravel-style API)
 */
final class QueryBuilder
{
    private string $table = '';

    /**
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * @var array<array<string, mixed>>
     */
    private array $wheres = [];

    /**
     * @var array<mixed>
     */
    private array $bindings = [];

    /**
     * @var array<JoinClause|array{type: string, table: string, first: string, operator: string, second: string}>
     */
    private array $joins = [];

    /**
     * @var array<array{column: string, direction: string}>
     */
    private array $orderBy = [];

    /**
     * @var array<string>
     */
    private array $groupBy = [];

    /**
     * @var array<self>
     */
    private array $unions = [];

    /**
     * @var array<bool>
     */
    private array $unionAll = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    private bool $distinct = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?QueryLogger $logger = null,
    ) {
    }

    /**
     * Set the table name
     */
    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Alias for table()
     */
    public function from(string $table): self
    {
        return $this->table($table);
    }

    /**
     * Set the columns to select
     *
     * @param array<string>|string $columns
     */
    public function select(array|string $columns = ['*']): self
    {
        if (is_array($columns)) {
            $this->columns = $columns;
        } else {
            /** @var array<string> $args */
            $args = func_get_args();
            $this->columns = $args;
        }

        return $this;
    }

    /**
     * Add a column to select
     */
    public function addSelect(string ...$columns): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    /**
     * Add a subquery as a select column
     */
    public function selectSub(Closure $callback, string $as): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $sql = '(' . $subQuery->toSql() . ') as ' . $this->quoteIdentifier($as);

        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $sql;

        // Merge bindings from subquery
        $this->bindings = array_merge($this->bindings, $subQuery->getBindings());

        return $this;
    }

    /**
     * Add a raw select expression
     */
    public function selectRaw(string $expression): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $expression;

        return $this;
    }

    /**
     * Set distinct flag
     */
    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Add a WHERE clause (Laravel-style)
     *
     * Supports:
     * - where('column', $value) - implicit '='
     * - where('column', '>', $value) - with operator
     * - where(['column' => $value, ...]) - array of conditions
     * - where(function ($q) { ... }) - nested conditions
     *
     * @param string|array<string, mixed>|array<array<mixed>>|Closure $column
     */
    public function where(
        string|array|Closure $column,
        mixed $operatorOrValue = null,
        mixed $value = null,
    ): self {
        // Closure for nested conditions
        if ($column instanceof Closure) {
            return $this->whereNested($column, 'AND');
        }

        // Array of conditions
        if (is_array($column)) {
            return $this->whereArray($column);
        }

        // Determine operator and value
        if ($value === null && $operatorOrValue !== null) {
            // Two arguments: where('column', $value)
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            // Three arguments: where('column', '>', $value)
            $operator = is_string($operatorOrValue) ? strtoupper($operatorOrValue) : '=';
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE clause
     *
     * @param string|array<string, mixed>|array<array<mixed>>|Closure $column
     */
    public function orWhere(
        string|array|Closure $column,
        mixed $operatorOrValue = null,
        mixed $value = null,
    ): self {
        // Closure for nested conditions
        if ($column instanceof Closure) {
            return $this->whereNested($column, 'OR');
        }

        // Array of conditions
        if (is_array($column)) {
            return $this->orWhereArray($column);
        }

        // Determine operator and value
        if ($value === null && $operatorOrValue !== null) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = is_string($operatorOrValue) ? strtoupper($operatorOrValue) : '=';
        }

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a WHERE column comparison (column = column)
     */
    public function whereColumn(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operatorOrSecond;
            $operator = '=';
        } else {
            $operator = strtoupper($operatorOrSecond);
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE column comparison
     */
    public function orWhereColumn(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operatorOrSecond;
            $operator = '=';
        } else {
            $operator = strtoupper($operatorOrSecond);
        }

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add a WHERE IN clause
     *
     * @param array<mixed>|Closure $values
     */
    public function whereIn(string $column, array|Closure $values): self
    {
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, 'AND', false);
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT IN clause
     *
     * @param array<mixed>|Closure $values
     */
    public function whereNotIn(string $column, array|Closure $values): self
    {
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, 'AND', true);
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE NULL clause
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'null',
            'column' => $column,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'null',
            'column' => $column,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NULL clause
     */
    public function orWhereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'null',
            'column' => $column,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NOT NULL clause
     */
    public function orWhereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'null',
            'column' => $column,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE BETWEEN clause
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'between',
            'column' => $column,
            'values' => [$min, $max],
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT BETWEEN clause
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'between',
            'column' => $column,
            'values' => [$min, $max],
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE LIKE clause
     */
    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    /**
     * Add an OR WHERE LIKE clause
     */
    public function orWhereLike(string $column, string $pattern): self
    {
        return $this->orWhere($column, 'LIKE', $pattern);
    }

    /**
     * Add an OR WHERE BETWEEN clause
     */
    public function orWhereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'between',
            'column' => $column,
            'values' => [$min, $max],
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NOT BETWEEN clause
     */
    public function orWhereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'between',
            'column' => $column,
            'values' => [$min, $max],
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE IN clause
     *
     * @param array<mixed>|Closure $values
     */
    public function orWhereIn(string $column, array|Closure $values): self
    {
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, 'OR', false);
        }

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NOT IN clause
     *
     * @param array<mixed>|Closure $values
     */
    public function orWhereNotIn(string $column, array|Closure $values): self
    {
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, 'OR', true);
        }

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE EXISTS clause
     */
    public function whereExists(Closure $callback): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'exists',
            'query' => $subQuery,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT EXISTS clause
     */
    public function whereNotExists(Closure $callback): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'exists',
            'query' => $subQuery,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE EXISTS clause
     */
    public function orWhereExists(Closure $callback): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'exists',
            'query' => $subQuery,
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE NOT EXISTS clause
     */
    public function orWhereNotExists(Closure $callback): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'exists',
            'query' => $subQuery,
            'not' => true,
        ];

        return $this;
    }

    /**
     * Add a raw WHERE clause
     *
     * @param array<mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * Add an OR raw WHERE clause
     *
     * @param array<mixed> $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * Add an INNER JOIN
     */
    public function join(
        string $table,
        string|Closure|null $firstOrClosure = null,
        ?string $operatorOrSecond = null,
        ?string $second = null,
    ): self {
        return $this->addJoin('INNER', $table, $firstOrClosure, $operatorOrSecond, $second);
    }

    /**
     * Add a LEFT JOIN
     */
    public function leftJoin(
        string $table,
        string|Closure|null $firstOrClosure = null,
        ?string $operatorOrSecond = null,
        ?string $second = null,
    ): self {
        return $this->addJoin('LEFT', $table, $firstOrClosure, $operatorOrSecond, $second);
    }

    /**
     * Add a RIGHT JOIN
     */
    public function rightJoin(
        string $table,
        string|Closure|null $firstOrClosure = null,
        ?string $operatorOrSecond = null,
        ?string $second = null,
    ): self {
        return $this->addJoin('RIGHT', $table, $firstOrClosure, $operatorOrSecond, $second);
    }

    /**
     * Add an ORDER BY clause
     *
     * @param string|array<array{0: string, 1?: string}>|array<string, string> $column
     */
    public function orderBy(string|array $column, string $direction = 'ASC'): self
    {
        // Array syntax: [['col', 'desc'], ['col2', 'asc']] or ['col' => 'desc']
        if (is_array($column)) {
            foreach ($column as $key => $value) {
                if (is_int($key)) {
                    // Indexed array: ['col', 'direction']
                    $col = is_array($value) ? $value[0] : $value;
                    $dir = is_array($value) && isset($value[1]) ? $value[1] : 'ASC';
                } else {
                    // Associative: 'col' => 'direction'
                    $col = $key;
                    $dir = is_string($value) ? $value : 'ASC';
                }
                $this->orderBy[] = [
                    'column' => $col,
                    'direction' => strtoupper($dir),
                ];
            }

            return $this;
        }

        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    /**
     * Add an ORDER BY ASC clause
     */
    public function orderByAsc(string $column): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Add an ORDER BY DESC clause
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Add a GROUP BY clause
     *
     * @param array<string>|string $columns
     */
    public function groupBy(array|string $columns): self
    {
        $this->groupBy = array_merge(
            $this->groupBy,
            is_array($columns) ? $columns : [$columns],
        );

        return $this;
    }

    /**
     * Set the LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;

        return $this;
    }

    /**
     * Set the OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;

        return $this;
    }

    /**
     * Alias for limit()
     */
    public function take(int $count): self
    {
        return $this->limit($count);
    }

    /**
     * Alias for offset()
     */
    public function skip(int $count): self
    {
        return $this->offset($count);
    }

    /**
     * Add a UNION
     */
    public function union(self $query): self
    {
        $this->unions[] = $query;
        $this->unionAll[] = false;

        return $this;
    }

    /**
     * Add a UNION ALL
     */
    public function unionAll(self $query): self
    {
        $this->unions[] = $query;
        $this->unionAll[] = true;

        return $this;
    }

    /**
     * Apply callback if condition is truthy
     *
     * @template TReturn
     *
     * @param mixed $condition
     * @param Closure(self, mixed): (self|TReturn) $callback
     * @param (Closure(self): (self|TReturn))|null $default
     */
    public function when(mixed $condition, Closure $callback, ?Closure $default = null): self
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Apply callback if condition is falsy
     *
     * @param Closure(self): self $callback
     */
    public function unless(mixed $condition, Closure $callback): self
    {
        if (!$condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Apply callback and return self (for side effects)
     *
     * @param Closure(self): void $callback
     */
    public function tap(Closure $callback): self
    {
        $callback($this);

        return $this;
    }

    /**
     * Execute the SELECT query and get all results
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $stmt = $this->executeSelect();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result !== false ? $result : [];
    }

    /**
     * Execute the SELECT query and get the first result
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $originalLimit = $this->limitValue;
        $this->limitValue = 1;

        $stmt = $this->executeSelect();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->limitValue = $originalLimit;

        return is_array($result) ? $result : null;
    }

    /**
     * Get the count of rows
     */
    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $this->columns = ["COUNT({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        $aggregate = $result['aggregate'] ?? 0;

        return is_numeric($aggregate) ? (int) $aggregate : 0;
    }

    /**
     * Check if any rows exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if no rows exist
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Execute an UPDATE query
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        $sql = $this->buildUpdateSql($values);

        return $this->executeStatement($sql);
    }

    /**
     * Execute a DELETE query
     */
    public function delete(): int
    {
        $sql = $this->buildDeleteSql();

        return $this->executeStatement($sql);
    }

    /**
     * Insert a row
     *
     * @param array<string, mixed> $values
     */
    public function insert(array $values): bool
    {
        $sql = $this->buildInsertSql($values);

        return $this->executeStatement($sql) > 0;
    }

    /**
     * Get the SQL string
     */
    public function toSql(): string
    {
        return $this->buildSelectSql();
    }

    /**
     * Get the bindings
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        $this->bindings = [];
        $this->buildWhereClause();

        return $this->bindings;
    }

    /**
     * Reset the query builder
     */
    public function reset(): self
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->bindings = [];
        $this->joins = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->unions = [];
        $this->unionAll = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->distinct = false;

        return $this;
    }

    /**
     * Clone the query builder
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Create a new query builder instance
     */
    public function newQuery(): self
    {
        return new self($this->connection, $this->logger);
    }

    // =========================================================================
    // PRIVATE METHODS
    // =========================================================================

    /**
     * Add nested WHERE conditions
     */
    private function whereNested(Closure $callback, string $boolean): self
    {
        $query = $this->newQuery();
        $query->table($this->table);
        $callback($query);

        if (!empty($query->wheres)) {
            $this->wheres[] = [
                'type' => $boolean,
                'kind' => 'nested',
                'query' => $query,
            ];
        }

        return $this;
    }

    /**
     * Add WHERE conditions from array
     *
     * @param array<string, mixed>|array<array<mixed>> $conditions
     */
    private function whereArray(array $conditions): self
    {
        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                // Indexed array: ['column', 'operator', 'value'] or ['column', 'value']
                if (is_array($value)) {
                    if (count($value) === 3 && is_string($value[0])) {
                        $this->where($value[0], $value[1], $value[2]);
                    } elseif (count($value) === 2 && is_string($value[0])) {
                        $this->where($value[0], $value[1]);
                    }
                }
            } else {
                // Associative: 'column' => 'value'
                $this->where($key, $value);
            }
        }

        return $this;
    }

    /**
     * Add OR WHERE conditions from array
     *
     * @param array<string, mixed>|array<array<mixed>> $conditions
     */
    private function orWhereArray(array $conditions): self
    {
        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                if (is_array($value)) {
                    if (count($value) === 3 && is_string($value[0])) {
                        $this->orWhere($value[0], $value[1], $value[2]);
                    } elseif (count($value) === 2 && is_string($value[0])) {
                        $this->orWhere($value[0], $value[1]);
                    }
                }
            } else {
                $this->orWhere($key, $value);
            }
        }

        return $this;
    }

    /**
     * Add WHERE IN with subquery
     */
    private function whereInSub(string $column, Closure $callback, string $boolean, bool $not): self
    {
        $subQuery = $this->newQuery();
        $callback($subQuery);

        $this->wheres[] = [
            'type' => $boolean,
            'kind' => 'in_sub',
            'column' => $column,
            'query' => $subQuery,
            'not' => $not,
        ];

        return $this;
    }

    /**
     * Add a JOIN clause
     */
    private function addJoin(
        string $type,
        string $table,
        string|Closure|null $firstOrClosure,
        ?string $operatorOrSecond,
        ?string $second,
    ): self {
        // Closure-based join with complex conditions
        if ($firstOrClosure instanceof Closure) {
            $join = new JoinClause($type, $table);
            $firstOrClosure($join);
            $this->joins[] = $join;

            return $this;
        }

        // Simple join
        if ($firstOrClosure !== null) {
            if ($second === null && $operatorOrSecond !== null) {
                $second = $operatorOrSecond;
                $operator = '=';
            } else {
                $operator = $operatorOrSecond ?? '=';
            }

            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'first' => $firstOrClosure,
                'operator' => $operator,
                'second' => $second ?? '',
            ];
        }

        return $this;
    }

    /**
     * Execute the SELECT query
     */
    private function executeSelect(): PDOStatement
    {
        $sql = $this->buildSelectSql();

        $startTime = microtime(true);

        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->bindings);

        $this->logQuery($sql, $this->bindings, microtime(true) - $startTime);

        return $stmt;
    }

    /**
     * Execute a statement and return affected rows
     */
    private function executeStatement(string $sql): int
    {
        $startTime = microtime(true);

        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->bindings);

        $this->logQuery($sql, $this->bindings, microtime(true) - $startTime);

        return $stmt->rowCount();
    }

    /**
     * Build the SELECT SQL
     */
    private function buildSelectSql(): string
    {
        $this->bindings = [];

        $sql = 'SELECT ';

        if ($this->distinct) {
            $sql .= 'DISTINCT ';
        }

        $sql .= implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->quoteIdentifier($this->table);
        $sql .= $this->buildJoinsClause();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitClause();
        $sql .= $this->buildUnionsClause();

        return $sql;
    }

    /**
     * Build the UPDATE SQL
     *
     * @param array<string, mixed> $values
     */
    private function buildUpdateSql(array $values): string
    {
        $this->bindings = [];

        $sets = [];
        foreach ($values as $column => $value) {
            $sets[] = $this->quoteIdentifier($column) . ' = ?';
            $this->bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->quoteIdentifier($this->table);
        $sql .= ' SET ' . implode(', ', $sets);
        $sql .= $this->buildWhereClause();

        return $sql;
    }

    /**
     * Build the DELETE SQL
     */
    private function buildDeleteSql(): string
    {
        $this->bindings = [];

        $sql = 'DELETE FROM ' . $this->quoteIdentifier($this->table);
        $sql .= $this->buildWhereClause();

        return $sql;
    }

    /**
     * Build the INSERT SQL
     *
     * @param array<string, mixed> $values
     */
    private function buildInsertSql(array $values): string
    {
        $this->bindings = [];

        $columns = array_keys($values);
        $placeholders = array_fill(0, count($values), '?');
        $this->bindings = array_values($values);

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($this->table);
        $sql .= ' (' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns)) . ')';
        $sql .= ' VALUES (' . implode(', ', $placeholders) . ')';

        return $sql;
    }

    /**
     * Build the JOINs clause
     */
    private function buildJoinsClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            if ($join instanceof JoinClause) {
                $sql .= $this->buildComplexJoin($join);
            } else {
                $sql .= sprintf(
                    ' %s JOIN %s ON %s %s %s',
                    $join['type'],
                    $this->quoteIdentifier($join['table']),
                    $join['first'],
                    $join['operator'],
                    $join['second'],
                );
            }
        }

        return $sql;
    }

    /**
     * Build a complex join with multiple conditions
     */
    private function buildComplexJoin(JoinClause $join): string
    {
        $sql = sprintf(
            ' %s JOIN %s ON ',
            $join->getType(),
            $this->quoteIdentifier($join->getTable()),
        );

        $conditions = $join->getConditions();
        $first = true;

        foreach ($conditions as $condition) {
            if (!$first) {
                $sql .= ' ' . $condition['type'] . ' ';
            }
            $first = false;

            if ($condition['isColumn']) {
                $sql .= sprintf(
                    '%s %s %s',
                    $condition['first'],
                    $condition['operator'],
                    $condition['second'],
                );
            } elseif ($condition['operator'] === 'IS NULL' || $condition['operator'] === 'IS NOT NULL') {
                $sql .= sprintf('%s %s', $this->quoteIdentifier($condition['first']), $condition['operator']);
            } else {
                $sql .= sprintf(
                    '%s %s ?',
                    $this->quoteIdentifier($condition['first']),
                    $condition['operator'],
                );
            }
        }

        // Add join bindings
        $this->bindings = array_merge($this->bindings, $join->getBindings());

        return $sql;
    }

    /**
     * Build the WHERE clause
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $first = true;

        foreach ($this->wheres as $where) {
            if (!$first) {
                $type = is_string($where['type'] ?? null) ? $where['type'] : 'AND';
                $sql .= ' ' . $type . ' ';
            }
            $first = false;

            $sql .= $this->buildWhereCondition($where);
        }

        return $sql;
    }

    /**
     * Build a single WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildWhereCondition(array $where): string
    {
        $kind = isset($where['kind']) && is_string($where['kind']) ? $where['kind'] : 'basic';

        return match ($kind) {
            'basic' => $this->buildBasicWhere($where),
            'column' => $this->buildColumnWhere($where),
            'in' => $this->buildInWhere($where),
            'in_sub' => $this->buildInSubWhere($where),
            'null' => $this->buildNullWhere($where),
            'between' => $this->buildBetweenWhere($where),
            'exists' => $this->buildExistsWhere($where),
            'nested' => $this->buildNestedWhere($where),
            'raw' => $this->buildRawWhere($where),
            default => '',
        };
    }

    /**
     * Build basic WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildBasicWhere(array $where): string
    {
        $columnValue = $where['column'] ?? '';
        $column = $this->quoteIdentifier(is_string($columnValue) ? $columnValue : '');
        $operator = is_string($where['operator'] ?? null) ? $where['operator'] : '=';
        $this->bindings[] = $where['value'] ?? null;

        return "{$column} {$operator} ?";
    }

    /**
     * Build column comparison WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildColumnWhere(array $where): string
    {
        $firstValue = $where['first'] ?? '';
        $secondValue = $where['second'] ?? '';
        $first = $this->quoteIdentifier(is_string($firstValue) ? $firstValue : '');
        $second = $this->quoteIdentifier(is_string($secondValue) ? $secondValue : '');
        $operator = is_string($where['operator'] ?? null) ? $where['operator'] : '=';

        return "{$first} {$operator} {$second}";
    }

    /**
     * Build IN WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildInWhere(array $where): string
    {
        $columnValue = $where['column'] ?? '';
        $column = $this->quoteIdentifier(is_string($columnValue) ? $columnValue : '');
        $values = is_array($where['values'] ?? null) ? $where['values'] : [];
        $not = (bool) ($where['not'] ?? false);
        $operator = $not ? 'NOT IN' : 'IN';

        if (empty($values)) {
            return $not ? '1 = 1' : '1 = 0';
        }

        $placeholders = array_fill(0, count($values), '?');
        $this->bindings = array_merge($this->bindings, $values);

        return "{$column} {$operator} (" . implode(', ', $placeholders) . ')';
    }

    /**
     * Build IN subquery WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildInSubWhere(array $where): string
    {
        $columnValue = $where['column'] ?? '';
        $column = $this->quoteIdentifier(is_string($columnValue) ? $columnValue : '');
        /** @var self $subQuery */
        $subQuery = $where['query'];
        $not = (bool) ($where['not'] ?? false);
        $operator = $not ? 'NOT IN' : 'IN';

        $subSql = $subQuery->toSql();
        $this->bindings = array_merge($this->bindings, $subQuery->getBindings());

        return "{$column} {$operator} ({$subSql})";
    }

    /**
     * Build NULL WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildNullWhere(array $where): string
    {
        $columnValue = $where['column'] ?? '';
        $column = $this->quoteIdentifier(is_string($columnValue) ? $columnValue : '');
        $not = (bool) ($where['not'] ?? false);
        $operator = $not ? 'IS NOT NULL' : 'IS NULL';

        return "{$column} {$operator}";
    }

    /**
     * Build BETWEEN WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildBetweenWhere(array $where): string
    {
        $columnValue = $where['column'] ?? '';
        $column = $this->quoteIdentifier(is_string($columnValue) ? $columnValue : '');
        $values = is_array($where['values'] ?? null) ? $where['values'] : [null, null];
        $not = (bool) ($where['not'] ?? false);
        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';

        $this->bindings[] = $values[0] ?? null;
        $this->bindings[] = $values[1] ?? null;

        return "{$column} {$operator} ? AND ?";
    }

    /**
     * Build EXISTS WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildExistsWhere(array $where): string
    {
        /** @var self $subQuery */
        $subQuery = $where['query'];
        $not = (bool) ($where['not'] ?? false);
        $operator = $not ? 'NOT EXISTS' : 'EXISTS';

        $subSql = $subQuery->toSql();
        $this->bindings = array_merge($this->bindings, $subQuery->getBindings());

        return "{$operator} ({$subSql})";
    }

    /**
     * Build nested WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildNestedWhere(array $where): string
    {
        /** @var self $query */
        $query = $where['query'];

        // Temporarily build the nested wheres
        $nestedBindings = [];
        $nestedSql = '';
        $first = true;

        foreach ($query->wheres as $nestedWhere) {
            if (!$first) {
                $type = is_string($nestedWhere['type'] ?? null) ? $nestedWhere['type'] : 'AND';
                $nestedSql .= ' ' . $type . ' ';
            }
            $first = false;

            // Build each nested condition
            $query->bindings = [];
            $conditionSql = $query->buildWhereCondition($nestedWhere);
            $nestedSql .= $conditionSql;
            $nestedBindings = array_merge($nestedBindings, $query->bindings);
        }

        $this->bindings = array_merge($this->bindings, $nestedBindings);

        return '(' . $nestedSql . ')';
    }

    /**
     * Build raw WHERE condition
     *
     * @param array<string, mixed> $where
     */
    private function buildRawWhere(array $where): string
    {
        $bindings = is_array($where['bindings'] ?? null) ? $where['bindings'] : [];
        $this->bindings = array_merge($this->bindings, $bindings);

        $sql = $where['sql'] ?? '';

        return is_string($sql) ? $sql : '';
    }

    /**
     * Build the GROUP BY clause
     */
    private function buildGroupByClause(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', array_map([$this, 'quoteIdentifier'], $this->groupBy));
    }

    /**
     * Build the ORDER BY clause
     */
    private function buildOrderByClause(): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        $parts = array_map(
            fn (array $order) => $this->quoteIdentifier($order['column']) . ' ' . $order['direction'],
            $this->orderBy,
        );

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Build the LIMIT clause
     */
    private function buildLimitClause(): string
    {
        $sql = '';

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    /**
     * Build the UNION clause
     */
    private function buildUnionsClause(): string
    {
        if (empty($this->unions)) {
            return '';
        }

        $sql = '';
        foreach ($this->unions as $index => $union) {
            $unionType = ($this->unionAll[$index] ?? false) ? 'UNION ALL' : 'UNION';
            $sql .= ' ' . $unionType . ' ' . $union->toSql();
            $this->bindings = array_merge($this->bindings, $union->getBindings());
        }

        return $sql;
    }

    /**
     * Quote an identifier (table/column name)
     */
    private function quoteIdentifier(string $identifier): string
    {
        // Handle table.column format
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(fn ($part) => "`{$part}`", $parts));
        }

        // Don't quote if it contains special characters (expressions)
        if (preg_match('/[()* ]/', $identifier)) {
            return $identifier;
        }

        return "`{$identifier}`";
    }

    /**
     * Log a query
     *
     * @param array<mixed> $bindings
     */
    private function logQuery(string $sql, array $bindings, float $duration): void
    {
        $this->logger?->log($sql, $bindings, $duration);
    }
}
