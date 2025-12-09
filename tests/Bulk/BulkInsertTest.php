<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Bulk;

use MethorZ\SwiftDb\Bulk\BulkInsert;
use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use PHPUnit\Framework\TestCase;

final class BulkInsertTest extends TestCase
{
    private function createBulkInsert(): BulkInsert
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        // Create a bulk insert without a real connection (we won't flush)
        return new class ($connection, 'product', 1000) extends BulkInsert {
            // Override destructor to prevent auto-flush
            public function __destruct()
            {
                // Don't auto-flush in tests
            }
        };
    }

    public function testAddRowIncrementsPendingCount(): void
    {
        $bulk = $this->createBulkInsert();

        $this->assertEquals(0, $bulk->getPendingCount());

        $bulk->add([
            'product_name' => 'Test Product',
            'product_price' => 9.99,
        ]);

        $this->assertEquals(1, $bulk->getPendingCount());

        $bulk->add([
            'product_name' => 'Another Product',
            'product_price' => 19.99,
        ]);

        $this->assertEquals(2, $bulk->getPendingCount());
    }

    public function testIgnoreModeIsSet(): void
    {
        $bulk = $this->createBulkInsert();
        $result = $bulk->ignore();

        // Should return self for fluent interface
        $this->assertSame($bulk, $result);
    }

    public function testAddManyAddsMultipleRows(): void
    {
        $bulk = $this->createBulkInsert();

        $bulk->addMany([
            ['product_name' => 'Product 1', 'product_price' => 10.0],
            ['product_name' => 'Product 2', 'product_price' => 20.0],
            ['product_name' => 'Product 3', 'product_price' => 30.0],
        ]);

        $this->assertEquals(3, $bulk->getPendingCount());
    }

    public function testGetTotalAffectedReturnsZeroInitially(): void
    {
        $bulk = $this->createBulkInsert();

        $this->assertEquals(0, $bulk->getTotalAffected());
    }

    public function testBatchSizeIsSetViaConstructor(): void
    {
        $config = new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
        );
        $connection = new Connection($config);

        // Custom batch size via constructor
        $bulk = new class ($connection, 'product', 500) extends BulkInsert {
            public function __destruct()
            {
                // Don't auto-flush in tests
            }

            public function getBatchSize(): int
            {
                return $this->batchSize;
            }
        };

        $this->assertEquals(500, $bulk->getBatchSize());
    }

    public function testAddWithEntityExtractsData(): void
    {
        $bulk = $this->createBulkInsert();

        // Use TestProduct which implements EntityInterface
        $entity = new \MethorZ\SwiftDb\Tests\Entity\TestProduct();
        $entity->name = 'Entity Product';
        $entity->price = 49.99;

        $bulk->add($entity);

        $this->assertEquals(1, $bulk->getPendingCount());
    }

    public function testIgnoreAndAddMany(): void
    {
        $bulk = $this->createBulkInsert();

        $result = $bulk
            ->ignore()
            ->addMany([
                ['product_name' => 'Product A', 'product_price' => 5.0],
                ['product_name' => 'Product B', 'product_price' => 15.0],
            ]);

        $this->assertSame($bulk, $result);
        $this->assertEquals(2, $bulk->getPendingCount());
    }

    public function testFluentChaining(): void
    {
        $bulk = $this->createBulkInsert();

        $result = $bulk
            ->ignore()
            ->add(['product_name' => 'Test', 'product_price' => 1.0]);

        $this->assertSame($bulk, $result);
        $this->assertEquals(1, $bulk->getPendingCount());
    }

    public function testIgnoreCanBeToggled(): void
    {
        $bulk = $this->createBulkInsert();

        // Enable ignore
        $result = $bulk->ignore(true);
        $this->assertSame($bulk, $result);

        // Disable ignore
        $result = $bulk->ignore(false);
        $this->assertSame($bulk, $result);
    }
}
