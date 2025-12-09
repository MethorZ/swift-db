<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when entity operations fail
 */
final class EntityException extends DatabaseException
{
    public static function notFound(string $entityClass, mixed $id): self
    {
        $idString = is_scalar($id) || $id instanceof \Stringable ? (string) $id : 'unknown';

        return new self(
            sprintf('Entity [%s] with ID [%s] not found', $entityClass, $idString),
        );
    }

    public static function invalidData(string $entityClass, string $reason): self
    {
        return new self(
            sprintf('Invalid data for entity [%s]: %s', $entityClass, $reason),
        );
    }

    public static function notPersisted(string $entityClass): self
    {
        return new self(
            sprintf('Entity [%s] has not been persisted yet', $entityClass),
        );
    }
}
