<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Cache;

use MethorZ\SwiftDb\Entity\EntityInterface;

/**
 * Tracks loaded entities to ensure single instance per identity
 *
 * Optional feature - when enabled, the same database row will always
 * return the same object instance within a request lifecycle.
 *
 * WARNING: Disable for bulk operations as it increases memory usage.
 */
final class IdentityMap
{
    /**
     * @var array<class-string, array<int|string, EntityInterface>>
     */
    private array $entities = [];

    /**
     * @var array<class-string, int>
     */
    private array $hitCount = [];

    /**
     * @var array<class-string, int>
     */
    private array $missCount = [];

    /**
     * Get an entity from the map
     *
     * @param class-string $entityClass
     * @param int|string $id
     */
    public function get(string $entityClass, int|string $id): ?EntityInterface
    {
        if (isset($this->entities[$entityClass][$id])) {
            $this->hitCount[$entityClass] = ($this->hitCount[$entityClass] ?? 0) + 1;

            return $this->entities[$entityClass][$id];
        }

        $this->missCount[$entityClass] = ($this->missCount[$entityClass] ?? 0) + 1;

        return null;
    }

    /**
     * Add an entity to the map
     *
     * @param class-string $entityClass
     * @param int|string $id
     */
    public function set(string $entityClass, int|string $id, EntityInterface $entity): void
    {
        $this->entities[$entityClass][$id] = $entity;
    }

    /**
     * Check if an entity exists in the map
     *
     * @param class-string $entityClass
     * @param int|string $id
     */
    public function has(string $entityClass, int|string $id): bool
    {
        return isset($this->entities[$entityClass][$id]);
    }

    /**
     * Remove an entity from the map (after delete)
     *
     * @param class-string $entityClass
     * @param int|string $id
     */
    public function remove(string $entityClass, int|string $id): void
    {
        unset($this->entities[$entityClass][$id]);
    }

    /**
     * Clear the map
     *
     * @param class-string|null $entityClass Clear specific class or all if null
     */
    public function clear(?string $entityClass = null): void
    {
        if ($entityClass === null) {
            $this->entities = [];
            $this->hitCount = [];
            $this->missCount = [];
        } else {
            $this->entities[$entityClass] = [];
            unset($this->hitCount[$entityClass], $this->missCount[$entityClass]);
        }
    }

    /**
     * Get the number of entities in the map
     *
     * @param class-string|null $entityClass
     */
    public function count(?string $entityClass = null): int
    {
        if ($entityClass === null) {
            return array_sum(array_map('count', $this->entities));
        }

        return count($this->entities[$entityClass] ?? []);
    }

    /**
     * Get statistics for debugging/monitoring
     *
     * @return array{
     *     total: int,
     *     by_class: array<class-string, int>,
     *     hits: array<class-string, int>,
     *     misses: array<class-string, int>,
     * }
     */
    public function getStats(): array
    {
        $byClass = [];
        foreach ($this->entities as $class => $entities) {
            $byClass[$class] = count($entities);
        }

        return [
            'total' => $this->count(),
            'by_class' => $byClass,
            'hits' => $this->hitCount,
            'misses' => $this->missCount,
        ];
    }
}
