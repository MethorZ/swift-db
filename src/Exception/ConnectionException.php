<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when database connection fails
 */
final class ConnectionException extends DatabaseException
{
    public static function failedToConnect(string $dsn, string $reason): self
    {
        return new self(
            sprintf('Failed to connect to database [%s]: %s', $dsn, $reason),
        );
    }

    public static function connectionLost(string $reason): self
    {
        return new self(
            sprintf('Database connection lost: %s', $reason),
        );
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self(
            sprintf('Invalid database configuration: %s', $message),
        );
    }
}
