<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Query;

/**
 * Join clause builder for complex join conditions
 */
final class JoinClause
{
    private string $type;

    private string $table;

    /**
     * @var array<array{type: string, first: string, operator: string, second: string|null, isColumn: bool}>
     */
    private array $conditions = [];

    /**
     * @var array<mixed>
     */
    private array $bindings = [];

    public function __construct(string $type, string $table)
    {
        $this->type = $type;
        $this->table = $table;
    }

    /**
     * Add an ON condition (column = column)
     */
    public function on(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operatorOrSecond;
            $operator = '=';
        } else {
            $operator = $operatorOrSecond;
        }

        $this->conditions[] = [
            'type' => 'AND',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'isColumn' => true,
        ];

        return $this;
    }

    /**
     * Add an OR ON condition
     */
    public function orOn(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operatorOrSecond;
            $operator = '=';
        } else {
            $operator = $operatorOrSecond;
        }

        $this->conditions[] = [
            'type' => 'OR',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'isColumn' => true,
        ];

        return $this;
    }

    /**
     * Add a WHERE condition to the join (column = value)
     */
    public function where(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if ($value === null && $operatorOrValue !== null) {
            $actualValue = $operatorOrValue;
            $operator = '=';
        } else {
            $actualValue = $value;
            $operator = is_string($operatorOrValue) ? $operatorOrValue : '=';
        }

        $this->conditions[] = [
            'type' => 'AND',
            'first' => $column,
            'operator' => $operator,
            'second' => null,
            'isColumn' => false,
        ];
        $this->bindings[] = $actualValue;

        return $this;
    }

    /**
     * Add an OR WHERE condition to the join
     */
    public function orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if ($value === null && $operatorOrValue !== null) {
            $actualValue = $operatorOrValue;
            $operator = '=';
        } else {
            $actualValue = $value;
            $operator = is_string($operatorOrValue) ? $operatorOrValue : '=';
        }

        $this->conditions[] = [
            'type' => 'OR',
            'first' => $column,
            'operator' => $operator,
            'second' => null,
            'isColumn' => false,
        ];
        $this->bindings[] = $actualValue;

        return $this;
    }

    /**
     * Add a WHERE NULL condition
     */
    public function whereNull(string $column): self
    {
        $this->conditions[] = [
            'type' => 'AND',
            'first' => $column,
            'operator' => 'IS NULL',
            'second' => null,
            'isColumn' => false,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL condition
     */
    public function whereNotNull(string $column): self
    {
        $this->conditions[] = [
            'type' => 'AND',
            'first' => $column,
            'operator' => 'IS NOT NULL',
            'second' => null,
            'isColumn' => false,
        ];

        return $this;
    }

    /**
     * Get the join type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the conditions
     *
     * @return array<array{type: string, first: string, operator: string, second: string|null, isColumn: bool}>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get the bindings
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
