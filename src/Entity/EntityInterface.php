<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Entity;

/**
 * Interface for all database entities
 */
interface EntityInterface
{
    /**
     * Get the table name for this entity
     */
    public static function getTableName(): string;

    /**
     * Get the primary key column name
     */
    public static function getPrimaryKeyColumn(): string;

    /**
     * Get the mapping of property names to column names
     *
     * @return array<string, string> [propertyName => columnName]
     */
    public function getColumnMapping(): array;

    /**
     * Get the primary key value
     */
    public function getId(): mixed;

    /**
     * Set the primary key value
     */
    public function setId(mixed $id): void;

    /**
     * Check if the entity has been persisted
     */
    public function isPersisted(): bool;

    /**
     * Mark the entity as persisted
     */
    public function markPersisted(): void;

    /**
     * Check if the entity has been modified since loading
     */
    public function isDirty(): bool;

    /**
     * Get the fields that have been modified
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function getDirtyFields(): array;

    /**
     * Mark the entity as clean (no modifications)
     */
    public function markClean(): void;

    /**
     * Hydrate the entity from database data
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): void;

    /**
     * Extract the entity data for database storage
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function extract(): array;

    /**
     * Extract only dirty fields for partial updates
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function extractDirty(): array;
}
