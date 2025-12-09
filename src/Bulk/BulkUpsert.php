<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Bulk;

/**
 * High-performance bulk upsert (INSERT ... ON DUPLICATE KEY UPDATE)
 *
 * Builds multi-row INSERT with ON DUPLICATE KEY UPDATE:
 * INSERT INTO table (a, b, c) VALUES (1, 2, 3), (4, 5, 6)
 * ON DUPLICATE KEY UPDATE b = VALUES(b), c = VALUES(c)
 */
final class BulkUpsert extends BulkInsert
{
    /**
     * Columns to update on duplicate key
     *
     * @var array<string, string> [columnName => expression]
     */
    protected array $updateColumns = [];

    /**
     * Set columns to update on duplicate key
     *
     * @param array<int|string, string> $columns Column names or [column => expression]
     */
    public function onDuplicateKeyUpdate(array $columns): self
    {
        foreach ($columns as $key => $value) {
            if (is_int($key)) {
                // Simple column: use VALUES(column)
                $this->updateColumns[$value] = "VALUES(`{$value}`)";
            } else {
                // Custom expression: column => expression
                $this->updateColumns[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Add a column to update with a custom expression
     */
    public function updateColumn(string $column, string $expression): self
    {
        $this->updateColumns[$column] = $expression;

        return $this;
    }

    /**
     * Update column by incrementing its value
     */
    public function incrementOnDuplicate(string $column): self
    {
        $this->updateColumns[$column] = "`{$column}` + VALUES(`{$column}`)";

        return $this;
    }

    /**
     * Set updated_at to NOW() on duplicate
     */
    public function touchUpdatedOnDuplicate(string $column = 'updated_at'): self
    {
        $this->updateColumns[$column] = 'NOW()';

        return $this;
    }

    /**
     * Build the INSERT ... ON DUPLICATE KEY UPDATE SQL
     */
    protected function buildSql(): string
    {
        $sql = parent::buildSql();

        if (!empty($this->updateColumns)) {
            $updates = [];
            foreach ($this->updateColumns as $column => $expression) {
                $updates[] = "`{$column}` = {$expression}";
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        return $sql;
    }
}
