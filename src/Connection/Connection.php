<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Connection;

use MethorZ\SwiftDb\Exception\ConnectionException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Single database connection wrapper with lazy initialization and reconnection support
 */
final class Connection
{
    private ?PDO $pdo = null;

    private int $reconnectAttempts = 0;

    private const MAX_RECONNECT_ATTEMPTS = 3;

    /**
     * Patterns that indicate a connection was lost
     */
    private const GONE_AWAY_PATTERNS = [
        'server has gone away',
        'Lost connection',
        'Error while sending',
        'decryption failed or bad record mac',
        'Connection timed out',
        'Connection refused',
        'Broken pipe',
    ];

    /**
     * MySQL error codes that indicate connection loss
     */
    private const GONE_AWAY_CODES = [
        2006, // CR_SERVER_GONE_ERROR
        2013, // CR_SERVER_LOST
    ];

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly string $name = 'default',
    ) {
    }

    /**
     * Get the PDO connection (lazy initialization)
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        /** @var PDO */
        return $this->pdo;
    }

    /**
     * Get the connection name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if the connection is established
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Establish the connection
     */
    public function connect(): void
    {
        try {
            $this->pdo = new PDO(
                $this->config->dsn,
                $this->config->username,
                $this->config->password,
                $this->config->getPdoOptions(),
            );
            $this->reconnectAttempts = 0;
        } catch (PDOException $e) {
            throw ConnectionException::failedToConnect($this->config->dsn, $e->getMessage());
        }
    }

    /**
     * Disconnect from the database
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Reconnect to the database
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Execute an operation with automatic reconnection on connection loss
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function executeWithReconnect(callable $operation): mixed
    {
        while (true) {
            try {
                return $operation();
            } catch (PDOException $e) {
                if ($this->isConnectionLost($e) && $this->reconnectAttempts < self::MAX_RECONNECT_ATTEMPTS) {
                    $this->reconnectAttempts++;
                    $this->reconnect();

                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Check if the exception indicates a lost connection
     */
    public function isConnectionLost(PDOException $e): bool
    {
        // Check error code
        if (in_array((int) $e->getCode(), self::GONE_AWAY_CODES, true)) {
            return true;
        }

        // Check error info
        $errorInfo = $e->errorInfo ?? [];
        if (in_array($errorInfo[1] ?? 0, self::GONE_AWAY_CODES, true)) {
            return true;
        }

        // Check message patterns
        $message = $e->getMessage();
        foreach (self::GONE_AWAY_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Check if a transaction is active
     */
    public function inTransaction(): bool
    {
        return $this->pdo !== null && $this->pdo->inTransaction();
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Prepare a SQL statement with automatic reconnection on connection loss
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->executeWithReconnect(function () use ($sql): PDOStatement {
            $stmt = $this->getPdo()->prepare($sql);

            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement: ' . $sql);
            }

            return $stmt;
        });
    }

    /**
     * Execute a raw SQL query and return the statement (for SELECT without parameters)
     *
     * For parameterized queries, use prepare() + execute() or the convenience
     * methods execute(), fetchOne(), fetchAll() instead.
     */
    public function query(string $sql): PDOStatement
    {
        return $this->executeWithReconnect(function () use ($sql): PDOStatement {
            $stmt = $this->getPdo()->query($sql);

            if ($stmt === false) {
                throw new PDOException('Failed to execute query: ' . $sql);
            }

            return $stmt;
        });
    }

    /**
     * Execute a parameterized statement and return the number of affected rows
     *
     * Convenience method for INSERT, UPDATE, DELETE operations.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->executeWithReconnect(function () use ($sql, $params): int {
            $stmt = $this->getPdo()->prepare($sql);

            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement: ' . $sql);
            }

            $stmt->execute($params);

            return $stmt->rowCount();
        });
    }

    /**
     * Execute a parameterized SELECT and return the first row or null
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->executeWithReconnect(function () use ($sql, $params): ?array {
            $stmt = $this->getPdo()->prepare($sql);

            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement: ' . $sql);
            }

            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            /** @var array<string, mixed> $result */
            return $result;
        });
    }

    /**
     * Execute a parameterized SELECT and return all matching rows
     *
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->executeWithReconnect(function () use ($sql, $params): array {
            $stmt = $this->getPdo()->prepare($sql);

            if ($stmt === false) {
                throw new PDOException('Failed to prepare statement: ' . $sql);
            }

            $stmt->execute($params);

            /** @var list<array<string, mixed>> */
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
}
