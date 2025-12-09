<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Repository;

use MethorZ\SwiftDb\Entity\EntityInterface;
use MethorZ\SwiftDb\Query\QueryBuilder;

/**
 * Interface for entity repositories
 *
 * @template T of EntityInterface
 */
interface RepositoryInterface
{
    /**
     * Get the table name
     */
    public function getTableName(): string;

    /**
     * Get the entity class
     *
     * @return class-string<T>
     */
    public function getEntityClass(): string;

    /**
     * Create a new entity instance
     *
     * @return T
     */
    public function create(): EntityInterface;

    /**
     * Find an entity by its primary key
     *
     * @return T|null
     */
    public function find(mixed $id): ?EntityInterface;

    /**
     * Find an entity by its primary key or throw exception
     *
     * @return T
     *
     * @throws \MethorZ\SwiftDb\Exception\EntityException
     */
    public function findOrFail(mixed $id): EntityInterface;

    /**
     * Find entities by multiple IDs
     *
     * @param array<mixed> $ids
     *
     * @return array<T>
     */
    public function findMany(array $ids): array;

    /**
     * Find all entities
     *
     * @return array<T>
     */
    public function findAll(): array;

    /**
     * Save an entity (insert or update)
     *
     * @param T $entity
     */
    public function save(EntityInterface $entity): void;

    /**
     * Delete an entity
     *
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void;

    /**
     * Delete an entity by ID
     */
    public function deleteById(mixed $id): bool;

    /**
     * Create a query builder for this repository
     */
    public function query(): QueryBuilder;

    /**
     * Count all entities
     */
    public function count(): int;
}
