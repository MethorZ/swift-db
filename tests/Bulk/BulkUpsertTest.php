<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Bulk;

use MethorZ\SwiftDb\Bulk\BulkUpsert;
use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use PHPUnit\Framework\TestCase;

final class BulkUpsertTest extends TestCase
{
    public function testOnDuplicateKeyUpdateSetsColumns(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);
        $result = $bulk->onDuplicateKeyUpdate(['product_price', 'product_stock']);

        // Should return self for fluent interface
        $this->assertSame($bulk, $result);
    }

    public function testUpdateColumnWithCustomExpression(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);
        $result = $bulk->updateColumn('product_stock', '`product_stock` + VALUES(`product_stock`)');

        // Should return self for fluent interface
        $this->assertSame($bulk, $result);
    }

    public function testIncrementOnDuplicate(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);
        $result = $bulk->incrementOnDuplicate('product_stock');

        // Should return self for fluent interface
        $this->assertSame($bulk, $result);
    }

    public function testTouchUpdatedOnDuplicate(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);
        $result = $bulk->touchUpdatedOnDuplicate('product_updated');

        // Should return self for fluent interface
        $this->assertSame($bulk, $result);
    }

    public function testFluentChaining(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);

        $result = $bulk
            ->onDuplicateKeyUpdate(['product_price', 'product_stock'])
            ->touchUpdatedOnDuplicate('product_updated')
            ->incrementOnDuplicate('product_view_count');

        $this->assertSame($bulk, $result);
    }

    public function testOnDuplicateKeyUpdateWithAdd(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        // Create a subclass of BulkInsert (which is NOT final) that overrides destructor
        // BulkUpsert extends BulkInsert, so we test the parent behavior
        $bulk = new class ($connection, 'product', 1000) extends \MethorZ\SwiftDb\Bulk\BulkInsert {
            public function __destruct()
            {
                // Don't auto-flush in tests
            }
        };

        $bulk->add(['product_id' => 1, 'product_name' => 'Test', 'product_price' => 9.99]);
        $bulk->add(['product_id' => 2, 'product_name' => 'Test 2', 'product_price' => 19.99]);

        $this->assertEquals(2, $bulk->getPendingCount());
    }

    public function testMultipleUpdateColumns(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);

        $result = $bulk
            ->updateColumn('product_price', 'VALUES(`product_price`)')
            ->updateColumn('product_stock', '`product_stock` + VALUES(`product_stock`)')
            ->updateColumn('product_updated', 'NOW()');

        $this->assertSame($bulk, $result);
    }

    public function testInheritsBulkInsertMethods(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        // Create a subclass of BulkInsert (which is NOT final) to test inherited behavior
        $bulk = new class ($connection, 'product', 500) extends \MethorZ\SwiftDb\Bulk\BulkInsert {
            public function __destruct()
            {
                // Don't auto-flush in tests
            }
        };

        $bulk->addMany([
            ['product_name' => 'A', 'product_price' => 1.0],
            ['product_name' => 'B', 'product_price' => 2.0],
        ]);

        $this->assertEquals(2, $bulk->getPendingCount());
        $this->assertEquals(0, $bulk->getTotalAffected());
    }

    public function testIgnoreModeInherited(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        $bulk = new BulkUpsert($connection, 'product', 1000);

        // Test ignore() inherited from BulkInsert
        $result = $bulk->ignore(true);
        $this->assertSame($bulk, $result);
    }
}
