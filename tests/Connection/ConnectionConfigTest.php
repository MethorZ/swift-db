<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Connection;

use MethorZ\SwiftDb\Connection\ConnectionConfig;
use PHPUnit\Framework\TestCase;

final class ConnectionConfigTest extends TestCase
{
    public function testConstructor(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: 'secret',
        );

        $this->assertEquals('mysql:host=localhost;dbname=test', $config->dsn);
        $this->assertEquals('root', $config->username);
        $this->assertEquals('secret', $config->password);
    }

    public function testFromArray(): void
    {
        $config = ConnectionConfig::fromArray([
            'dsn' => 'mysql:host=db;dbname=mydb',
            'username' => 'app_user',
            'password' => 'app_pass',
        ]);

        $this->assertEquals('mysql:host=db;dbname=mydb', $config->dsn);
        $this->assertEquals('app_user', $config->username);
        $this->assertEquals('app_pass', $config->password);
    }

    public function testFromArrayWithDefaults(): void
    {
        $config = ConnectionConfig::fromArray([
            'dsn' => 'mysql:host=localhost;dbname=test',
        ]);

        $this->assertEquals('mysql:host=localhost;dbname=test', $config->dsn);
        $this->assertEquals('', $config->username);
        $this->assertEquals('', $config->password);
    }

    public function testFromArrayThrowsExceptionWithoutDsn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN is required');

        ConnectionConfig::fromArray([
            'username' => 'user',
        ]);
    }

    public function testGetPdoOptionsReturnsDefaults(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
        );

        $options = $config->getPdoOptions();

        $this->assertArrayHasKey(\PDO::ATTR_ERRMODE, $options);
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $options[\PDO::ATTR_ERRMODE]);

        $this->assertArrayHasKey(\PDO::ATTR_DEFAULT_FETCH_MODE, $options);
        $this->assertEquals(\PDO::FETCH_ASSOC, $options[\PDO::ATTR_DEFAULT_FETCH_MODE]);

        $this->assertArrayHasKey(\PDO::ATTR_EMULATE_PREPARES, $options);
        $this->assertFalse($options[\PDO::ATTR_EMULATE_PREPARES]);
    }

    public function testGetPdoOptionsMergesCustomOptions(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'root',
            password: '',
            options: [
                \PDO::ATTR_TIMEOUT => 30,
            ],
        );

        $options = $config->getPdoOptions();

        // Custom option should be present
        $this->assertArrayHasKey(\PDO::ATTR_TIMEOUT, $options);
        $this->assertEquals(30, $options[\PDO::ATTR_TIMEOUT]);

        // Default options should still be present
        $this->assertArrayHasKey(\PDO::ATTR_ERRMODE, $options);
    }
}
