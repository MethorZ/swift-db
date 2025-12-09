<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Bulk;

use MethorZ\SwiftDb\Tests\Integration\Entity\Product;
use MethorZ\SwiftDb\Tests\Integration\IntegrationTestCase;
use MethorZ\SwiftDb\Tests\Integration\Repository\ProductRepository;

/**
 * Integration tests for bulk operations
 */
final class BulkOperationsIntegrationTest extends IntegrationTestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductRepository($this->connection, $this->logger);
    }

    public function testBulkInsert(): void
    {
        $bulk = $this->repository->bulkInsert();

        for ($i = 1; $i <= 10; $i++) {
            $product = $this->repository->create();
            $product->name = "Bulk Product {$i}";
            $product->price = $i * 10.0;
            $product->stock = $i * 5;
            $product->touchTimestamps();
            $product->generateUuid();
            $bulk->add($product);
        }

        $affected = $bulk->flush();

        $this->assertEquals(10, $affected);
        $this->assertEquals(10, $this->getTableCount('product'));
    }

    public function testBulkInsertWithBatching(): void
    {
        // Create bulk insert with small batch size
        $bulk = $this->repository->bulkInsert();

        // Use reflection to set a smaller batch size for testing
        $reflection = new \ReflectionProperty($bulk, 'batchSize');
        $reflection->setValue($bulk, 5);

        for ($i = 1; $i <= 12; $i++) {
            $product = $this->repository->create();
            $product->name = "Batch Product {$i}";
            $product->price = $i * 5.0;
            $product->stock = $i;
            $product->touchTimestamps();
            $product->generateUuid();
            $bulk->add($product);
        }

        // After adding 12 items with batch size 5, 2 batches should have been flushed automatically
        // (5 + 5 = 10), leaving 2 pending
        $this->assertEquals(2, $bulk->getPendingCount());

        // Flush remaining
        $bulk->flush();

        $this->assertEquals(12, $this->getTableCount('product'));
    }

    public function testBulkInsertIgnore(): void
    {
        // Insert a product first
        $existing = $this->repository->create();
        $existing->name = 'Existing Product';
        $existing->price = 10.0;
        $existing->stock = 5;
        $existing->uuid = '00000000-0000-0000-0000-000000000001';
        $this->repository->save($existing);

        // Now try to bulk insert with same UUID (should be ignored due to unique constraint)
        $bulk = $this->repository->bulkInsert()->ignore();

        $product1 = $this->repository->create();
        $product1->name = 'Should Be Ignored';
        $product1->price = 20.0;
        $product1->stock = 10;
        $product1->uuid = '00000000-0000-0000-0000-000000000001'; // Same UUID
        $product1->touchTimestamps();
        $bulk->add($product1);

        $product2 = $this->repository->create();
        $product2->name = 'Should Be Inserted';
        $product2->price = 30.0;
        $product2->stock = 15;
        $product2->uuid = '00000000-0000-0000-0000-000000000002';
        $product2->touchTimestamps();
        $bulk->add($product2);

        $affected = $bulk->flush();

        // Only 1 should be inserted (the non-duplicate)
        $this->assertEquals(1, $affected);
        $this->assertEquals(2, $this->getTableCount('product'));

        // Verify the existing product was not changed
        $this->assertRowExists('product', [
            'product_uuid' => '00000000-0000-0000-0000-000000000001',
            'product_name' => 'Existing Product',
        ]);
    }

    public function testBulkUpsertOnDuplicateKeyUpdate(): void
    {
        $this->seedProducts();

        $bulk = $this->repository->bulkUpsert()
            ->onDuplicateKeyUpdate(['product_price', 'product_stock']);

        // Update existing product (by UUID)
        $existing = $this->repository->findOrFail(1);
        $existing->price = 999.99;
        $existing->stock = 999;
        $bulk->add($existing);

        // Add new product
        $new = $this->repository->create();
        $new->name = 'New Upsert Product';
        $new->price = 50.0;
        $new->stock = 25;
        $new->touchTimestamps();
        $new->generateUuid();
        $bulk->add($new);

        $affected = $bulk->flush();

        // 2 rows affected (1 insert + 1 update)
        $this->assertGreaterThanOrEqual(2, $affected);

        // Verify existing product was updated
        $updated = $this->repository->findOrFail(1);
        $this->assertEquals(999.99, $updated->price);
        $this->assertEquals(999, $updated->stock);

        // Verify new product was inserted
        $this->assertEquals(4, $this->getTableCount('product'));
    }

    public function testBulkUpsertWithIncrementOnDuplicate(): void
    {
        $this->seedProducts();

        $bulk = $this->repository->bulkUpsert()
            ->incrementOnDuplicate('product_stock');

        // Update existing product - stock should be incremented
        $existing = $this->repository->findOrFail(1);
        $originalStock = $existing->stock;
        $existing->stock = 10; // This will be added to existing stock
        $bulk->add($existing);

        $bulk->flush();

        // Verify stock was incremented
        $updated = $this->repository->findOrFail(1);
        $this->assertEquals($originalStock + 10, $updated->stock);
    }

    public function testBulkInsertWithEntity(): void
    {
        $bulk = $this->repository->bulkInsert();

        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $product = new Product();
            $product->name = "Entity Product {$i}";
            $product->price = $i * 15.0;
            $product->stock = $i * 3;
            $product->touchTimestamps();
            $product->generateUuid();
            $products[] = $product;
        }

        $bulk->addMany($products);
        $affected = $bulk->flush();

        $this->assertEquals(5, $affected);
        $this->assertEquals(5, $this->getTableCount('product'));
    }

    public function testBulkInsertPerformance(): void
    {
        // Test that bulk insert is faster than individual inserts
        $bulk = $this->repository->bulkInsert();

        $start = microtime(true);

        for ($i = 1; $i <= 100; $i++) {
            $product = $this->repository->create();
            $product->name = "Performance Test Product {$i}";
            $product->price = $i * 1.5;
            $product->stock = $i;
            $product->touchTimestamps();
            $product->generateUuid();
            $bulk->add($product);
        }
        $bulk->flush();

        $bulkTime = microtime(true) - $start;

        // Verify all rows were inserted
        $this->assertEquals(100, $this->getTableCount('product'));

        // Should complete quickly (< 1 second for 100 rows)
        $this->assertLessThan(1.0, $bulkTime, 'Bulk insert took too long');
    }
}
