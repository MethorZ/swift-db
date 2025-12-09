<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Connection;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    public function testIsConnectedReturnsFalseInitially(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config, 'test');

        $this->assertFalse($connection->isConnected());
    }

    public function testGetNameReturnsConnectionName(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config, 'my-connection');

        $this->assertEquals('my-connection', $connection->getName());
    }

    public function testDefaultConnectionName(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config);

        $this->assertEquals('default', $connection->getName());
    }

    public function testIsConnectionLostDetectsGoneAwayPatterns(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config);

        // Test various "gone away" messages
        $goneAwayException = new \PDOException('MySQL server has gone away');
        $this->assertTrue($connection->isConnectionLost($goneAwayException));

        $lostConnectionException = new \PDOException('Lost connection to MySQL server');
        $this->assertTrue($connection->isConnectionLost($lostConnectionException));

        $timeoutException = new \PDOException('Connection timed out');
        $this->assertTrue($connection->isConnectionLost($timeoutException));

        // Test a normal exception
        $normalException = new \PDOException('Syntax error in SQL');
        $this->assertFalse($connection->isConnectionLost($normalException));
    }

    public function testInTransactionReturnsFalseWhenNotConnected(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config);

        $this->assertFalse($connection->inTransaction());
    }

    public function testDisconnectSetsPdoToNull(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $connection = new Connection($config);
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());
    }
}
