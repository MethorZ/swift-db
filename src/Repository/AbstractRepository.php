<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Repository;

use MethorZ\SwiftDb\Bulk\BulkInsert;
use MethorZ\SwiftDb\Bulk\BulkUpsert;
use MethorZ\SwiftDb\Cache\IdentityMap;
use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Entity\EntityInterface;
use MethorZ\SwiftDb\Exception\DeadlockException;
use MethorZ\SwiftDb\Exception\DuplicateEntryException;
use MethorZ\SwiftDb\Exception\EntityException;
use MethorZ\SwiftDb\Exception\OptimisticLockException;
use MethorZ\SwiftDb\Exception\QueryException;
use MethorZ\SwiftDb\Query\QueryBuilder;
use MethorZ\SwiftDb\Query\QueryLogger;
use MethorZ\SwiftDb\Trait\TimestampsTrait;
use MethorZ\SwiftDb\Trait\UuidTrait;
use MethorZ\SwiftDb\Trait\VersionTrait;
use PDO;
use PDOException;

/**
 * Base repository implementation
 *
 * @template T of EntityInterface
 *
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected int $deadlockRetries = 3;

    protected int $bulkBatchSize = 1000;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly ?QueryLogger $logger = null,
        protected readonly ?IdentityMap $identityMap = null,
    ) {
    }

    /**
     * Get the table name
     */
    abstract public function getTableName(): string;

    /**
     * Get the entity class
     *
     * @return class-string<T>
     */
    abstract public function getEntityClass(): string;

    /**
     * Get the primary key column
     */
    public function getPrimaryKeyColumn(): string
    {
        $entityClass = $this->getEntityClass();

        return $entityClass::getPrimaryKeyColumn();
    }

    /**
     * Create a new entity instance
     *
     * @return T
     */
    public function create(): EntityInterface
    {
        $class = $this->getEntityClass();

        return new $class();
    }

    /**
     * Find an entity by ID
     *
     * @return T|null
     */
    public function find(mixed $id): ?EntityInterface
    {
        // Check identity map first (if enabled and ID is valid type)
        if ($this->identityMap !== null && $this->isValidIdentityMapKey($id)) {
            $cached = $this->identityMap->get($this->getEntityClass(), $id);

            if ($cached !== null) {
                return $cached;
            }
        }

        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = ? LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKeyColumn(),
        );

        $row = $this->fetchOne($sql, [$id]);

        if ($row === null) {
            return null;
        }

        $entity = $this->hydrateEntity($row);

        // Store in identity map (if enabled)
        $entityId = $entity->getId();

        if ($this->identityMap !== null && $this->isValidIdentityMapKey($entityId)) {
            $this->identityMap->set($this->getEntityClass(), $entityId, $entity);
        }

        return $entity;
    }

    /**
     * Find an entity by ID or throw exception
     *
     * @return T
     */
    public function findOrFail(mixed $id): EntityInterface
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw EntityException::notFound($this->getEntityClass(), $id);
        }

        return $entity;
    }

    /**
     * Find entities by multiple IDs
     *
     * @param array<mixed> $ids
     *
     * @return array<T>
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $entityClass = $this->getEntityClass();

        /** @var array<int|string, EntityInterface> $entities */
        $entities = [];
        $idsToLoad = [];

        // Check identity map for cached entities (if enabled)
        if ($this->identityMap !== null) {
            foreach ($ids as $id) {
                if (!$this->isValidIdentityMapKey($id)) {
                    $idsToLoad[] = $id;

                    continue;
                }

                $cached = $this->identityMap->get($entityClass, $id);

                if ($cached !== null) {
                    $entities[$id] = $cached;
                } else {
                    $idsToLoad[] = $id;
                }
            }
        } else {
            $idsToLoad = $ids;
        }

        // Load remaining from database
        if (!empty($idsToLoad)) {
            $placeholders = implode(', ', array_fill(0, count($idsToLoad), '?'));
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE `%s` IN (%s)',
                $this->getTableName(),
                $this->getPrimaryKeyColumn(),
                $placeholders,
            );

            $rows = $this->fetchAll($sql, $idsToLoad);

            foreach ($rows as $row) {
                $entity = $this->hydrateEntity($row);
                $entityId = $entity->getId();

                if ($this->isValidIdentityMapKey($entityId)) {
                    $entities[$entityId] = $entity;

                    // Store in identity map (if enabled)
                    if ($this->identityMap !== null) {
                        $this->identityMap->set($entityClass, $entityId, $entity);
                    }
                }
            }
        }

        // Return in original order
        $result = [];

        foreach ($ids as $id) {
            if ($this->isValidIdentityMapKey($id) && isset($entities[$id])) {
                $result[] = $entities[$id];
            }
        }

        return $result;
    }

    /**
     * Find all entities
     *
     * @return array<T>
     */
    public function findAll(): array
    {
        $sql = sprintf('SELECT * FROM `%s`', $this->getTableName());
        $rows = $this->fetchAll($sql);

        return array_map(fn (array $row) => $this->hydrateEntity($row), $rows);
    }

    /**
     * Save an entity (insert or update)
     *
     * @param T $entity
     */
    public function save(EntityInterface $entity): void
    {
        $this->executeWithDeadlockRetry(function () use ($entity): void {
            // Handle UUID trait
            if ($this->hasUuidTrait($entity)) {
                $this->ensureUuid($entity);
            }

            // Handle timestamps trait
            if ($this->hasTimestampsTrait($entity)) {
                $this->touchTimestamps($entity);
            }

            if ($entity->isPersisted()) {
                $this->update($entity);
            } else {
                $this->insert($entity);
            }
        });
    }

    /**
     * Delete an entity
     *
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void
    {
        if (!$entity->isPersisted()) {
            throw EntityException::notPersisted($this->getEntityClass());
        }

        $id = $entity->getId();
        $this->deleteById($id);

        // Remove from identity map (if enabled)
        if ($this->identityMap !== null && $this->isValidIdentityMapKey($id)) {
            $this->identityMap->remove($this->getEntityClass(), $id);
        }
    }

    /**
     * Delete an entity by ID
     */
    public function deleteById(mixed $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `%s` = ?',
            $this->getTableName(),
            $this->getPrimaryKeyColumn(),
        );

        $affected = $this->execute($sql, [$id]);

        // Remove from identity map (if enabled)
        if ($affected > 0 && $this->identityMap !== null && $this->isValidIdentityMapKey($id)) {
            $this->identityMap->remove($this->getEntityClass(), $id);
        }

        return $affected > 0;
    }

    /**
     * Create a query builder
     */
    public function query(): QueryBuilder
    {
        return (new QueryBuilder($this->connection, $this->logger))
            ->table($this->getTableName());
    }

    /**
     * Count all entities
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Create a bulk insert helper
     */
    public function bulkInsert(): BulkInsert
    {
        return new BulkInsert(
            $this->connection,
            $this->getTableName(),
            $this->bulkBatchSize,
            $this->logger,
        );
    }

    /**
     * Create a bulk upsert helper
     */
    public function bulkUpsert(): BulkUpsert
    {
        return new BulkUpsert(
            $this->connection,
            $this->getTableName(),
            $this->bulkBatchSize,
            $this->logger,
        );
    }

    /**
     * Insert an entity
     */
    protected function insert(EntityInterface $entity): void
    {
        $data = $entity->extract();

        // Remove null primary key
        $pkColumn = $this->getPrimaryKeyColumn();
        if (isset($data[$pkColumn]) && $data[$pkColumn] === null) {
            unset($data[$pkColumn]);
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->getTableName(),
            implode(', ', array_map(fn ($col) => "`{$col}`", $columns)),
            implode(', ', $placeholders),
        );

        try {
            $this->execute($sql, array_values($data));
        } catch (PDOException $e) {
            if (DuplicateEntryException::isDuplicateEntry($e)) {
                throw DuplicateEntryException::fromPdoException($e, $sql, array_values($data));
            }

            throw $e;
        }

        // Set the auto-generated ID
        $lastId = $this->connection->lastInsertId();
        if ($lastId !== false && $lastId !== '0') {
            $entity->setId((int) $lastId);
        }

        $entity->markPersisted();

        // Store in identity map (if enabled)
        $entityId = $entity->getId();

        if ($this->identityMap !== null && $this->isValidIdentityMapKey($entityId)) {
            $this->identityMap->set($this->getEntityClass(), $entityId, $entity);
        }
    }

    /**
     * Update an entity
     */
    protected function update(EntityInterface $entity): void
    {
        // Only update dirty fields
        $data = $entity->extractDirty();

        if (empty($data)) {
            return;
        }

        $pkColumn = $this->getPrimaryKeyColumn();
        unset($data[$pkColumn]); // Don't update primary key

        if (empty($data)) {
            return;
        }

        $sets = [];
        $params = [];

        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $params[] = $value;
        }

        // Handle optimistic locking
        $whereClause = "`{$pkColumn}` = ?";
        $params[] = $entity->getId();

        $hasVersion = $this->hasVersionTrait($entity);
        $currentVersion = 0;
        $versionColumn = '';

        if ($hasVersion) {
            $versionColumn = $this->getVersionColumn($entity);
            $currentVersion = $this->getVersion($entity);

            $whereClause .= " AND `{$versionColumn}` = ?";
            $params[] = $currentVersion;

            // Increment version in the update
            $sets[] = "`{$versionColumn}` = `{$versionColumn}` + 1";
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->getTableName(),
            implode(', ', $sets),
            $whereClause,
        );

        $affected = $this->execute($sql, $params);

        // Check optimistic lock
        if ($hasVersion && $affected === 0) {
            throw OptimisticLockException::versionMismatch(
                $this->getEntityClass(),
                $entity->getId(),
                $currentVersion,
            );
        }

        // Increment local version
        if ($hasVersion) {
            $this->incrementVersion($entity);
        }

        $entity->markClean();
    }

    /**
     * Hydrate an entity from database row
     *
     * @param array<string, mixed> $data
     *
     * @return T
     */
    protected function hydrateEntity(array $data): EntityInterface
    {
        $entity = $this->create();
        $entity->hydrate($data);

        return $entity;
    }

    /**
     * Execute a query and return affected rows
     *
     * @param array<mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();
        } catch (PDOException $e) {
            if (DeadlockException::isDeadlock($e)) {
                throw DeadlockException::fromPdoException($e, $sql, $params);
            }

            throw QueryException::executionFailed($sql, $params, $e->getMessage());
        }

        $this->logger?->log($sql, $params, microtime(true) - $startTime);

        return $affected;
    }

    /**
     * Fetch a single row
     *
     * @param array<mixed> $params
     *
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw QueryException::executionFailed($sql, $params, $e->getMessage());
        }

        $this->logger?->log($sql, $params, microtime(true) - $startTime);

        return is_array($row) ? $row : null;
    }

    /**
     * Fetch all rows
     *
     * @param array<mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw QueryException::executionFailed($sql, $params, $e->getMessage());
        }

        $this->logger?->log($sql, $params, microtime(true) - $startTime);

        return $rows !== false ? $rows : [];
    }

    /**
     * Execute with deadlock retry
     *
     * @template TResult
     *
     * @param callable(): TResult $operation
     *
     * @return TResult
     */
    protected function executeWithDeadlockRetry(callable $operation): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return $operation();
            } catch (DeadlockException $e) {
                $attempts++;
                if ($attempts >= $this->deadlockRetries) {
                    throw $e;
                }

                // Exponential backoff with jitter
                usleep(random_int(10000, 100000) * $attempts);
            }
        }
    }

    /**
     * Check if entity uses TimestampsTrait
     */
    protected function hasTimestampsTrait(EntityInterface $entity): bool
    {
        return in_array(TimestampsTrait::class, class_uses($entity) ?: [], true);
    }

    /**
     * Check if entity uses UuidTrait
     */
    protected function hasUuidTrait(EntityInterface $entity): bool
    {
        return in_array(UuidTrait::class, class_uses($entity) ?: [], true);
    }

    /**
     * Check if entity uses VersionTrait
     */
    protected function hasVersionTrait(EntityInterface $entity): bool
    {
        return in_array(VersionTrait::class, class_uses($entity) ?: [], true);
    }

    /**
     * Ensure UUID is set on entity
     */
    protected function ensureUuid(EntityInterface $entity): void
    {
        if (method_exists($entity, 'ensureUuid')) {
            $entity->ensureUuid();
        }
    }

    /**
     * Touch timestamps on entity
     */
    protected function touchTimestamps(EntityInterface $entity): void
    {
        if (method_exists($entity, 'touchTimestamps')) {
            $entity->touchTimestamps();
        }
    }

    /**
     * Get version column from entity
     */
    protected function getVersionColumn(EntityInterface $entity): string
    {
        if (method_exists($entity, 'getVersionColumn')) {
            return $entity->getVersionColumn();
        }

        return $this->getTableName() . '_version';
    }

    /**
     * Get current version from entity
     */
    protected function getVersion(EntityInterface $entity): int
    {
        if (property_exists($entity, 'version') && is_int($entity->version)) {
            return $entity->version;
        }

        return 1;
    }

    /**
     * Increment version on entity
     */
    protected function incrementVersion(EntityInterface $entity): void
    {
        if (method_exists($entity, 'incrementVersion')) {
            $entity->incrementVersion();
        }
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Execute within a transaction
     *
     * @template TResult
     *
     * @param callable(): TResult $callback
     *
     * @return TResult
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * Check if a value is a valid identity map key (int or string)
     *
     * @phpstan-assert-if-true int|string $value
     */
    protected function isValidIdentityMapKey(mixed $value): bool
    {
        return is_int($value) || is_string($value);
    }

    /**
     * Clear the identity map for this repository's entity class
     *
     * Useful before bulk operations to free memory
     */
    public function clearIdentityMap(): void
    {
        $this->identityMap?->clear($this->getEntityClass());
    }

    /**
     * Check if identity map is enabled
     */
    public function hasIdentityMap(): bool
    {
        return $this->identityMap !== null;
    }

    /**
     * Get the identity map instance (if enabled)
     */
    public function getIdentityMap(): ?IdentityMap
    {
        return $this->identityMap;
    }
}
