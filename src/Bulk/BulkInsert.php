<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Bulk;

use DateTimeInterface;
use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Entity\EntityInterface;
use MethorZ\SwiftDb\Exception\QueryException;
use MethorZ\SwiftDb\Query\QueryLogger;
use PDOException;

/**
 * High-performance bulk insert with batching support
 *
 * Builds multi-row INSERT statements for maximum performance:
 * INSERT INTO table (a, b, c) VALUES (1, 2, 3), (4, 5, 6), (7, 8, 9)...
 */
class BulkInsert
{
    /**
     * @var array<string>
     */
    protected array $columns = [];

    /**
     * @var array<string>
     */
    protected array $valueSets = [];

    /**
     * @var array<mixed>
     */
    protected array $params = [];

    protected int $pendingCount = 0;

    protected int $totalAffected = 0;

    protected bool $ignoreErrors = false;

    /**
     * Maximum query size in bytes (8MB)
     */
    protected int $maxQuerySize = 8388608;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly string $table,
        protected readonly int $batchSize = 1000,
        protected readonly ?QueryLogger $logger = null,
    ) {
    }

    /**
     * Enable INSERT IGNORE mode
     */
    public function ignore(bool $ignore = true): self
    {
        $this->ignoreErrors = $ignore;

        return $this;
    }

    /**
     * Add a row to the batch
     *
     * @param array<string, mixed>|EntityInterface $row
     */
    public function add(array|EntityInterface $row): self
    {
        if ($row instanceof EntityInterface) {
            $row = $row->extract();
        }

        // Initialize columns from first row
        if (empty($this->columns)) {
            $this->columns = array_keys($row);
        }

        // Ensure row has same columns
        $values = [];
        foreach ($this->columns as $column) {
            $value = $row[$column] ?? null;
            $values[] = $this->convertValue($value);
        }

        // Build placeholders
        $placeholders = array_fill(0, count($values), '?');
        $this->valueSets[] = '(' . implode(', ', $placeholders) . ')';
        $this->params = array_merge($this->params, $values);
        $this->pendingCount++;

        // Auto-flush when batch is full
        if ($this->pendingCount >= $this->batchSize) {
            $this->flush();
        }

        return $this;
    }

    /**
     * Add multiple rows
     *
     * @param array<array<string, mixed>|EntityInterface> $rows
     */
    public function addMany(array $rows): self
    {
        foreach ($rows as $row) {
            $this->add($row);
        }

        return $this;
    }

    /**
     * Execute the pending inserts
     *
     * @return int Number of affected rows
     */
    public function flush(): int
    {
        if ($this->pendingCount === 0) {
            return 0;
        }

        $sql = $this->buildSql();
        $affected = $this->execute($sql, $this->params);

        $this->totalAffected += $affected;
        $this->reset();

        return $affected;
    }

    /**
     * Get the total number of affected rows across all batches
     */
    public function getTotalAffected(): int
    {
        return $this->totalAffected;
    }

    /**
     * Get the number of pending rows (not yet flushed)
     */
    public function getPendingCount(): int
    {
        return $this->pendingCount;
    }

    /**
     * Build the INSERT SQL
     */
    protected function buildSql(): string
    {
        $insert = $this->ignoreErrors ? 'INSERT IGNORE' : 'INSERT';
        $columns = implode(', ', array_map(fn ($col) => "`{$col}`", $this->columns));
        $values = implode(', ', $this->valueSets);

        return "{$insert} INTO `{$this->table}` ({$columns}) VALUES {$values}";
    }

    /**
     * Execute the SQL
     *
     * @param array<mixed> $params
     */
    protected function execute(string $sql, array $params): int
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();
        } catch (PDOException $e) {
            throw QueryException::executionFailed($sql, $params, $e->getMessage());
        }

        $this->logger?->log($sql, $params, microtime(true) - $startTime);

        return $affected;
    }

    /**
     * Reset the batch state
     */
    protected function reset(): void
    {
        $this->valueSets = [];
        $this->params = [];
        $this->pendingCount = 0;
    }

    /**
     * Convert a value for database storage
     */
    protected function convertValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * Destructor - ensure all rows are flushed
     */
    public function __destruct()
    {
        if ($this->pendingCount > 0) {
            $this->flush();
        }
    }
}
