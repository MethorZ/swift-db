<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when a deadlock is detected
 */
final class DeadlockException extends QueryException
{
    /**
     * MySQL deadlock error codes
     */
    private const DEADLOCK_ERROR_CODES = [
        1213, // ER_LOCK_DEADLOCK
        1205, // ER_LOCK_WAIT_TIMEOUT
    ];

    /**
     * @param array<mixed> $params
     */
    public static function fromPdoException(\PDOException $e, string $sql = '', array $params = []): self
    {
        return new self(
            sprintf('Database deadlock detected: %s', $e->getMessage()),
            $sql,
            $params,
            (int) $e->getCode(),
            $e,
        );
    }

    public static function isDeadlock(\PDOException $e): bool
    {
        $errorInfo = $e->errorInfo ?? [];

        return in_array($errorInfo[1] ?? 0, self::DEADLOCK_ERROR_CODES, true);
    }
}
