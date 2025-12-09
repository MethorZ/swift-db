<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Connection;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use MethorZ\SwiftDb\Connection\ConnectionManager;
use MethorZ\SwiftDb\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

final class ConnectionManagerTest extends TestCase
{
    public function testConstructorWithSingleConnection(): void
    {
        $manager = new ConnectionManager([
            'connections' => [
                'default' => [
                    'dsn' => 'mysql:host=localhost;dbname=test',
                    'username' => 'root',
                    'password' => 'secret',
                ],
            ],
        ]);

        $this->assertTrue($manager->hasConnection('default'));
        $this->assertFalse($manager->hasConnection('nonexistent'));
    }

    public function testConstructorWithMultipleConnections(): void
    {
        $manager = new ConnectionManager([
            'default' => 'master',
            'connections' => [
                'master' => [
                    'dsn' => 'mysql:host=master;dbname=test',
                    'username' => 'root',
                    'password' => 'secret',
                ],
                'slave' => [
                    'dsn' => 'mysql:host=slave;dbname=test',
                    'username' => 'readonly',
                    'password' => 'secret',
                ],
            ],
        ]);

        $this->assertTrue($manager->hasConnection('master'));
        $this->assertTrue($manager->hasConnection('slave'));
    }

    public function testGetConnectionReturnsConnection(): void
    {
        $manager = new ConnectionManager([
            'connections' => [
                'default' => [
                    'dsn' => 'mysql:host=localhost;dbname=test',
                    'username' => 'root',
                    'password' => '',
                ],
            ],
        ]);

        $connection = $manager->getConnection('default');

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals('default', $connection->getName());
    }

    public function testGetConnectionThrowsExceptionForUnknown(): void
    {
        $manager = new ConnectionManager([
            'connections' => [
                'default' => [
                    'dsn' => 'mysql:host=localhost;dbname=test',
                    'username' => 'root',
                    'password' => '',
                ],
            ],
        ]);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection [nonexistent] is not configured');

        $manager->getConnection('nonexistent');
    }

    public function testGetConnectionNames(): void
    {
        $manager = new ConnectionManager([
            'connections' => [
                'master' => [
                    'dsn' => 'mysql:host=master;dbname=test',
                    'username' => 'root',
                    'password' => '',
                ],
                'slave' => [
                    'dsn' => 'mysql:host=slave;dbname=test',
                    'username' => 'root',
                    'password' => '',
                ],
            ],
        ]);

        $names = $manager->getConnectionNames();

        $this->assertContains('master', $names);
        $this->assertContains('slave', $names);
        $this->assertCount(2, $names);
    }

    public function testAddConnection(): void
    {
        $manager = new ConnectionManager([
            'connections' => [],
        ]);

        $this->assertFalse($manager->hasConnection('new'));

        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $manager->addConnection('new', $config);

        $this->assertTrue($manager->hasConnection('new'));
    }

    public function testMasterSlaveConfiguration(): void
    {
        $manager = new ConnectionManager([
            'default' => 'master',
            'read_from' => 'slave',
            'write_to' => 'master',
            'connections' => [
                'master' => [
                    'dsn' => 'mysql:host=master;dbname=test',
                    'username' => 'root',
                    'password' => '',
                ],
                'slave' => [
                    'dsn' => 'mysql:host=slave;dbname=test',
                    'username' => 'readonly',
                    'password' => '',
                ],
            ],
        ]);

        $readConnection = $manager->getReadConnection();
        $writeConnection = $manager->getWriteConnection();
        $defaultConnection = $manager->getDefaultConnection();

        $this->assertEquals('slave', $readConnection->getName());
        $this->assertEquals('master', $writeConnection->getName());
        $this->assertEquals('master', $defaultConnection->getName());
    }
}
