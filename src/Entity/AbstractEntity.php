<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Entity;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Base class for all database entities
 *
 * Provides dirty tracking and hydration/extraction functionality
 */
abstract class AbstractEntity implements EntityInterface
{
    /**
     * Original data after hydration (for dirty tracking)
     *
     * @var array<string, mixed>
     */
    private array $originalData = [];

    /**
     * Whether the entity has been persisted to the database
     */
    private bool $persisted = false;

    /**
     * Get the primary key column name
     * Override in subclass if different from table_id convention
     */
    public static function getPrimaryKeyColumn(): string
    {
        return static::getTableName() . '_id';
    }

    /**
     * Get the ID value
     */
    public function getId(): mixed
    {
        $mapping = $this->getColumnMapping();
        $primaryKeyColumn = static::getPrimaryKeyColumn();

        // Find the property that maps to the primary key column
        $idProperty = array_search($primaryKeyColumn, $mapping, true);

        if ($idProperty !== false && is_string($idProperty) && property_exists($this, $idProperty)) {
            return $this->$idProperty;
        }

        return null;
    }

    /**
     * Set the ID value (typically after insert)
     */
    public function setId(mixed $id): void
    {
        $mapping = $this->getColumnMapping();
        $primaryKeyColumn = static::getPrimaryKeyColumn();

        $idProperty = array_search($primaryKeyColumn, $mapping, true);

        if ($idProperty !== false && is_string($idProperty) && property_exists($this, $idProperty)) {
            $this->$idProperty = $id;
        }
    }

    /**
     * Check if the entity has been persisted
     */
    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    /**
     * Mark the entity as persisted
     */
    public function markPersisted(): void
    {
        $this->persisted = true;
        $this->markClean();
    }

    /**
     * Check if the entity has been modified
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirtyFields());
    }

    /**
     * Get the fields that have been modified
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function getDirtyFields(): array
    {
        $current = $this->extract();

        return array_diff_assoc($current, $this->originalData);
    }

    /**
     * Mark the entity as clean
     */
    public function markClean(): void
    {
        $this->originalData = $this->extract();
    }

    /**
     * Hydrate the entity from database data
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): void
    {
        $mapping = $this->getColumnMapping();

        foreach ($mapping as $property => $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];

            // Type conversion based on property type
            $value = $this->convertToPropertyType($property, $value);

            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        $this->persisted = true;
        $this->originalData = $this->extract();
    }

    /**
     * Extract entity data for database storage
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function extract(): array
    {
        $data = [];
        $mapping = $this->getColumnMapping();

        foreach ($mapping as $property => $column) {
            if (!property_exists($this, $property)) {
                continue;
            }

            $value = $this->$property;

            // Type conversion for database
            $value = $this->convertToDatabaseType($value);

            $data[$column] = $value;
        }

        return $data;
    }

    /**
     * Extract only dirty fields for partial updates
     *
     * @return array<string, mixed> [columnName => value]
     */
    public function extractDirty(): array
    {
        if (!$this->isPersisted()) {
            return $this->extract();
        }

        return $this->getDirtyFields();
    }

    /**
     * Convert a database value to the expected property type
     */
    protected function convertToPropertyType(string $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Get the property type using reflection
        $reflection = new \ReflectionProperty($this, $property);
        $type = $reflection->getType();

        if ($type === null) {
            return $value;
        }

        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        if ($typeName === null) {
            return $value;
        }

        return match ($typeName) {
            'int' => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'bool' => (bool) $value,
            'string' => is_scalar($value) || $value instanceof \Stringable ? (string) $value : '',
            'array' => is_string($value) ? (json_decode($value, true) ?? []) : (array) $value,
            DateTimeImmutable::class, DateTimeInterface::class => $value instanceof DateTimeInterface
                ? $value
                : (is_string($value) ? new DateTimeImmutable($value) : new DateTimeImmutable()),
            default => $value,
        };
    }

    /**
     * Convert a property value to database format
     */
    protected function convertToDatabaseType(mixed $value): mixed
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
     * Create a new instance from database data
     *
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $entity = new static();
        $entity->hydrate($data);

        return $entity;
    }

    /**
     * Convert entity to array (public properties only)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        $mapping = $this->getColumnMapping();

        foreach ($mapping as $property => $column) {
            if (property_exists($this, $property)) {
                $data[$property] = $this->$property;
            }
        }

        return $data;
    }
}
