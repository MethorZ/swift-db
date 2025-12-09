<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Trait;

/**
 * Trait for entities with optimistic locking via version column
 *
 * When updating, the repository will check that the version matches
 * and increment it atomically.
 */
trait VersionTrait
{
    public int $version = 1;

    /**
     * Get the version column name for this entity
     */
    public function getVersionColumn(): string
    {
        return static::getTableName() . '_version';
    }

    /**
     * Get the version column mapping
     *
     * @return array<string, string>
     */
    protected function getVersionMapping(): array
    {
        return [
            'version' => $this->getVersionColumn(),
        ];
    }

    /**
     * Increment the version
     */
    public function incrementVersion(): void
    {
        $this->version++;
    }

    /**
     * Check if this entity uses optimistic locking
     */
    public function usesOptimisticLocking(): bool
    {
        return true;
    }
}
