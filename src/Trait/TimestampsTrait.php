<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Trait;

use DateTimeImmutable;

/**
 * Trait for entities with created_at and updated_at timestamps
 *
 * Uses table prefix convention: {table}_updated, {table}_created
 */
trait TimestampsTrait
{
    public ?DateTimeImmutable $updatedAt = null;

    public ?DateTimeImmutable $createdAt = null;

    /**
     * Get the timestamp column names for this entity
     *
     * @return array{updated: string, created: string}
     */
    public function getTimestampColumns(): array
    {
        $table = static::getTableName();

        return [
            'updated' => $table . '_updated',
            'created' => $table . '_created',
        ];
    }

    /**
     * Add timestamp columns to the column mapping
     * Call this in getColumnMapping() to include timestamp columns
     *
     * @return array<string, string>
     */
    protected function getTimestampMapping(): array
    {
        $columns = $this->getTimestampColumns();

        return [
            'updatedAt' => $columns['updated'],
            'createdAt' => $columns['created'],
        ];
    }

    /**
     * Set the updated timestamp to now
     */
    public function touchUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Set the created timestamp to now (if not already set)
     */
    public function touchCreated(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTimeImmutable();
        }
    }

    /**
     * Set both timestamps (for new entities)
     */
    public function touchTimestamps(): void
    {
        $now = new DateTimeImmutable();
        $this->updatedAt = $now;

        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }
    }
}
