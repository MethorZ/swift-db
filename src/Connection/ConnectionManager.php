<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Connection;

use MethorZ\SwiftDb\Exception\ConnectionException;

/**
 * Manages multiple database connections with master/slave support
 */
final class ConnectionManager
{
    /**
     * @var array<string, Connection>
     */
    private array $connections = [];

    private string $defaultConnection;

    private ?string $readConnection;

    private ?string $writeConnection;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $default = $config['default'] ?? 'default';
        $this->defaultConnection = is_string($default) ? $default : 'default';

        $readFrom = $config['read_from'] ?? null;
        $this->readConnection = is_string($readFrom) ? $readFrom : null;

        $writeTo = $config['write_to'] ?? null;
        $this->writeConnection = is_string($writeTo) ? $writeTo : null;

        $connectionsConfig = $config['connections'] ?? [];
        if (is_array($connectionsConfig)) {
            foreach ($connectionsConfig as $name => $connectionConfig) {
                if (is_string($name) && is_array($connectionConfig)) {
                    /** @var array<string, mixed> $connectionConfig */
                    $this->addConnection($name, ConnectionConfig::fromArray($connectionConfig));
                }
            }
        }
    }

    /**
     * Add a connection to the manager
     */
    public function addConnection(string $name, ConnectionConfig $config): void
    {
        $this->connections[$name] = new Connection($config, $name);
    }

    /**
     * Get a connection by name
     */
    public function getConnection(?string $name = null): Connection
    {
        $name ??= $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            throw ConnectionException::invalidConfiguration(
                sprintf('Connection [%s] is not configured', $name),
            );
        }

        return $this->connections[$name];
    }

    /**
     * Get the connection for read operations
     */
    public function getReadConnection(): Connection
    {
        return $this->getConnection($this->readConnection);
    }

    /**
     * Get the connection for write operations
     */
    public function getWriteConnection(): Connection
    {
        return $this->getConnection($this->writeConnection);
    }

    /**
     * Get the default connection
     */
    public function getDefaultConnection(): Connection
    {
        return $this->getConnection($this->defaultConnection);
    }

    /**
     * Check if a connection exists
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get all connection names
     *
     * @return array<string>
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Disconnect all connections
     */
    public function disconnectAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
    }

    /**
     * Reconnect all connections
     */
    public function reconnectAll(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isConnected()) {
                $connection->reconnect();
            }
        }
    }
}
