<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when optimistic locking fails (version mismatch)
 */
final class OptimisticLockException extends DatabaseException
{
    public static function versionMismatch(string $entityClass, mixed $id, int $expectedVersion): self
    {
        $idString = is_scalar($id) || $id instanceof \Stringable ? (string) $id : 'unknown';

        return new self(
            sprintf(
                'Entity [%s] with ID [%s] was modified by another process. Expected version: %d',
                $entityClass,
                $idString,
                $expectedVersion,
            ),
        );
    }
}
