<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Connection;

/**
 * Configuration for a single database connection
 */
final readonly class ConnectionConfig
{
    /**
     * Default PDO options for MySQL
     */
    private const DEFAULT_OPTIONS = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::MYSQL_ATTR_FOUND_ROWS => true,
    ];

    /**
     * @param array<int, mixed> $options
     */
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
        public array $options = [],
    ) {
    }

    /**
     * Create from array configuration
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            dsn: isset($config['dsn']) && is_string($config['dsn'])
                ? $config['dsn']
                : throw new \InvalidArgumentException('DSN is required'),
            username: isset($config['username']) && is_string($config['username'])
                ? $config['username']
                : '',
            password: isset($config['password']) && is_string($config['password'])
                ? $config['password']
                : '',
            options: isset($config['options']) && is_array($config['options'])
                ? $config['options']
                : [],
        );
    }

    /**
     * Get merged PDO options with defaults
     *
     * @return array<int, mixed>
     */
    public function getPdoOptions(): array
    {
        return $this->options + self::DEFAULT_OPTIONS;
    }
}
