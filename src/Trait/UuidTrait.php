<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Trait;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Trait for entities with UUID support (UUID v7 - time-ordered)
 *
 * UUID v7 is recommended for database primary keys because:
 * - Time-ordered: sequential inserts maintain index efficiency
 * - Still globally unique
 * - Contains timestamp information
 */
trait UuidTrait
{
    public ?string $uuid = null;

    /**
     * Get the UUID column name for this entity
     * Override if using a different column name
     */
    public function getUuidColumn(): string
    {
        return static::getTableName() . '_uuid';
    }

    /**
     * Get the UUID column mapping
     *
     * @return array<string, string>
     */
    protected function getUuidMapping(): array
    {
        return [
            'uuid' => $this->getUuidColumn(),
        ];
    }

    /**
     * Generate a new UUID v7
     */
    public function generateUuid(): void
    {
        $this->uuid = Uuid::uuid7()->toString();
    }

    /**
     * Get the UUID as a UuidInterface object
     */
    public function getUuidObject(): ?UuidInterface
    {
        if ($this->uuid === null) {
            return null;
        }

        return Uuid::fromString($this->uuid);
    }

    /**
     * Ensure UUID is set (generate if not)
     */
    public function ensureUuid(): void
    {
        if ($this->uuid === null) {
            $this->generateUuid();
        }
    }
}
