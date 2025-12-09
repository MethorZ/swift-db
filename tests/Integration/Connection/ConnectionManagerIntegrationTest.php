<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Connection;

use MethorZ\SwiftDb\Connection\ConnectionConfig;
use MethorZ\SwiftDb\Connection\ConnectionManager;
use MethorZ\SwiftDb\Query\QueryBuilder;
use MethorZ\SwiftDb\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for ConnectionManager with master/slave configuration
 *
 * Note: In a real master/slave setup, these would test against actual separate instances.
 * For this test, we use the same database but verify the connection routing logic.
 */
final class ConnectionManagerIntegrationTest extends IntegrationTestCase
{
    // =========================================================================
    // BASIC CONNECTION TESTS
    // =========================================================================

    public function testGetDefaultConnection(): void
    {
        $manager = $this->createConnectionManager();

        $connection = $manager->getDefaultConnection();

        $this->assertEquals('master', $connection->getName());
    }

    public function testGetNamedConnection(): void
    {
        $manager = $this->createConnectionManager();

        $masterConnection = $manager->getConnection('master');
        $slaveConnection = $manager->getConnection('slave');

        $this->assertEquals('master', $masterConnection->getName());
        $this->assertEquals('slave', $slaveConnection->getName());
    }

    public function testHasConnection(): void
    {
        $manager = $this->createConnectionManager();

        $this->assertTrue($manager->hasConnection('master'));
        $this->assertTrue($manager->hasConnection('slave'));
        $this->assertFalse($manager->hasConnection('nonexistent'));
    }

    public function testGetConnectionNames(): void
    {
        $manager = $this->createConnectionManager();

        $names = $manager->getConnectionNames();

        $this->assertContains('master', $names);
        $this->assertContains('slave', $names);
        $this->assertCount(2, $names);
    }

    // =========================================================================
    // READ/WRITE ROUTING TESTS
    // =========================================================================

    public function testReadConnectionRouting(): void
    {
        $manager = $this->createConnectionManager();

        $readConnection = $manager->getReadConnection();

        $this->assertEquals('slave', $readConnection->getName());
    }

    public function testWriteConnectionRouting(): void
    {
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();

        $this->assertEquals('master', $writeConnection->getName());
    }

    public function testReadConnectionCanPerformSelects(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $readConnection = $manager->getReadConnection();
        $builder = new QueryBuilder($readConnection);

        $results = $builder
            ->table('product')
            ->select('*')
            ->get();

        $this->assertCount(3, $results);
    }

    public function testWriteConnectionCanPerformInserts(): void
    {
        $this->seedCategories();
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();
        $builder = new QueryBuilder($writeConnection);

        $result = $builder
            ->table('product')
            ->insert([
                'product_uuid' => 'master-insert-uuid',
                'product_name' => 'Master Insert Test',
                'product_price' => 99.99,
                'product_stock' => 10,
                'product_category_id' => 1,
            ]);

        $this->assertTrue($result);
        $this->assertRowExists('product', ['product_name' => 'Master Insert Test']);
    }

    public function testWriteConnectionCanPerformUpdates(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();
        $builder = new QueryBuilder($writeConnection);

        $affected = $builder
            ->table('product')
            ->where('product_id', 1)
            ->update(['product_name' => 'Updated on Master']);

        $this->assertEquals(1, $affected);
        $this->assertRowExists('product', ['product_name' => 'Updated on Master']);
    }

    public function testWriteConnectionCanPerformDeletes(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();
        $builder = new QueryBuilder($writeConnection);

        $affected = $builder
            ->table('product')
            ->where('product_id', 1)
            ->delete();

        $this->assertEquals(1, $affected);
        $this->assertRowNotExists('product', ['product_id' => 1]);
    }

    // =========================================================================
    // CONNECTION MANAGEMENT TESTS
    // =========================================================================

    public function testAddConnectionDynamically(): void
    {
        $manager = $this->createConnectionManager();

        $this->assertFalse($manager->hasConnection('analytics'));

        $config = new ConnectionConfig(
            dsn: sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '33066',
                getenv('DB_DATABASE') ?: 'methorz_test',
            ),
            username: getenv('DB_USERNAME') ?: 'methorz',
            password: getenv('DB_PASSWORD') ?: 'methorz',
        );

        $manager->addConnection('analytics', $config);

        $this->assertTrue($manager->hasConnection('analytics'));

        // Verify the new connection works
        $analyticsConnection = $manager->getConnection('analytics');
        $this->assertEquals('analytics', $analyticsConnection->getName());
    }

    public function testDisconnectAll(): void
    {
        $manager = $this->createConnectionManager();

        // Force connections to be established
        $manager->getConnection('master')->getPdo();
        $manager->getConnection('slave')->getPdo();

        // Both should be connected
        $this->assertTrue($manager->getConnection('master')->isConnected());
        $this->assertTrue($manager->getConnection('slave')->isConnected());

        // Disconnect all
        $manager->disconnectAll();

        // Both should be disconnected
        $this->assertFalse($manager->getConnection('master')->isConnected());
        $this->assertFalse($manager->getConnection('slave')->isConnected());
    }

    public function testReconnectAll(): void
    {
        $manager = $this->createConnectionManager();

        // Force connections to be established
        $manager->getConnection('master')->getPdo();
        $manager->getConnection('slave')->getPdo();

        // Disconnect
        $manager->disconnectAll();

        // Reconnect (will reconnect on next getPdo() call)
        $manager->reconnectAll();

        // Connections should work again
        $masterBuilder = new QueryBuilder($manager->getConnection('master'));
        $results = $masterBuilder->table('category')->select('*')->get();
        $this->assertNotEmpty($results);
    }

    // =========================================================================
    // TRANSACTION TESTS WITH WRITE CONNECTION
    // =========================================================================

    public function testTransactionOnWriteConnection(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();

        $writeConnection->beginTransaction();

        try {
            $builder = new QueryBuilder($writeConnection);
            $builder->table('product')
                    ->where('product_id', 1)
                    ->update(['product_price' => 999.99]);

            // Verify change is visible within transaction
            $builder2 = new QueryBuilder($writeConnection);
            $result = $builder2->table('product')
                              ->where('product_id', 1)
                              ->first();

            $this->assertNotNull($result);
            $productPrice = $result['product_price'] ?? 0;
            $price = is_numeric($productPrice) ? (float) $productPrice : 0.0;
            $this->assertEqualsWithDelta(999.99, $price, 0.01);

            $writeConnection->commit();
        } catch (\Throwable $e) {
            $writeConnection->rollback();

            throw $e;
        }

        // Verify change persisted
        $this->assertRowExists('product', ['product_id' => 1]);
    }

    public function testTransactionRollbackOnWriteConnection(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $writeConnection = $manager->getWriteConnection();

        $writeConnection->beginTransaction();

        $builder = new QueryBuilder($writeConnection);
        $builder->table('product')
                ->where('product_id', 1)
                ->delete();

        // Product should be deleted within transaction
        $builder2 = new QueryBuilder($writeConnection);
        $result = $builder2->table('product')
                          ->where('product_id', 1)
                          ->first();
        $this->assertNull($result);

        // Rollback
        $writeConnection->rollback();

        // Product should be back
        $builder3 = new QueryBuilder($writeConnection);
        $result = $builder3->table('product')
                          ->where('product_id', 1)
                          ->first();
        $this->assertNotNull($result);
    }

    // =========================================================================
    // CONCURRENT ACCESS SIMULATION
    // =========================================================================

    public function testMultipleQueriesOnDifferentConnections(): void
    {
        $this->seedProducts();
        $manager = $this->createConnectionManager();

        $readConnection = $manager->getReadConnection();
        $writeConnection = $manager->getWriteConnection();

        // Read from slave
        $readBuilder = new QueryBuilder($readConnection);
        $initialCount = $readBuilder->table('product')->count();

        // Write to master
        $writeBuilder = new QueryBuilder($writeConnection);
        $writeBuilder->table('product')
                    ->insert([
                        'product_uuid' => 'concurrent-test-uuid',
                        'product_name' => 'Concurrent Test',
                        'product_price' => 10.00,
                        'product_stock' => 1,
                    ]);

        // Read again from slave (in real setup, there might be replication lag)
        $readBuilder2 = new QueryBuilder($readConnection);
        $newCount = $readBuilder2->table('product')->count();

        // Since we're using same DB, count should be higher
        $this->assertEquals($initialCount + 1, $newCount);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function createConnectionManager(): ConnectionManager
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '33066',
            getenv('DB_DATABASE') ?: 'methorz_test',
        );

        return new ConnectionManager([
            'default' => 'master',
            'read_from' => 'slave',
            'write_to' => 'master',
            'connections' => [
                'master' => [
                    'dsn' => $dsn,
                    'username' => getenv('DB_USERNAME') ?: 'methorz',
                    'password' => getenv('DB_PASSWORD') ?: 'methorz',
                ],
                'slave' => [
                    // In real setup, this would point to a replica
                    // For testing, we use the same database
                    'dsn' => $dsn,
                    'username' => getenv('DB_USERNAME') ?: 'methorz',
                    'password' => getenv('DB_PASSWORD') ?: 'methorz',
                ],
            ],
        ]);
    }
}
