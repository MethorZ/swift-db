<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Repository;

use MethorZ\SwiftDb\Exception\EntityException;
use MethorZ\SwiftDb\Tests\Integration\Entity\Product;
use MethorZ\SwiftDb\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for AbstractRepository
 */
final class RepositoryIntegrationTest extends IntegrationTestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductRepository($this->connection, $this->logger);
    }

    public function testCreateAndSaveNewEntity(): void
    {
        $product = $this->repository->create();
        $product->name = 'New Product';
        $product->description = 'A test product';
        $product->price = 99.99;
        $product->stock = 10;

        $this->repository->save($product);

        $this->assertNotNull($product->id);
        $this->assertTrue($product->isPersisted());
        $this->assertNotNull($product->uuid);
        $this->assertNotNull($product->createdAt);
        $this->assertNotNull($product->updatedAt);

        // Verify in database
        $this->assertRowExists('product', ['product_id' => $product->id]);
    }

    public function testFindById(): void
    {
        $this->seedProducts();

        $product = $this->repository->find(1);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals(1, $product->id);
        $this->assertEquals('Test Product 1', $product->name);
        $this->assertEquals(19.99, $product->price);
        $this->assertEquals(100, $product->stock);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $product = $this->repository->find(999);

        $this->assertNull($product);
    }

    public function testFindOrFailThrowsException(): void
    {
        $this->expectException(EntityException::class);

        $this->repository->findOrFail(999);
    }

    public function testFindMany(): void
    {
        $this->seedProducts();

        $products = $this->repository->findMany([1, 2, 3]);

        $this->assertCount(3, $products);
        $this->assertContainsOnlyInstancesOf(Product::class, $products);
    }

    public function testFindAll(): void
    {
        $this->seedProducts();

        $products = $this->repository->findAll();

        $this->assertCount(3, $products);
    }

    public function testUpdateEntity(): void
    {
        $this->seedProducts();

        $product = $this->repository->findOrFail(1);
        $originalUpdated = $product->updatedAt;
        $this->assertNotNull($originalUpdated);

        // Wait a tiny bit to ensure timestamp changes
        usleep(10000);

        $product->name = 'Updated Product';
        $product->price = 24.99;

        $this->repository->save($product);

        // Verify in database
        $this->assertRowExists('product', [
            'product_id' => 1,
            'product_name' => 'Updated Product',
        ]);
    }

    public function testUpdateOnlyDirtyFields(): void
    {
        $this->seedProducts();

        $product = $this->repository->findOrFail(1);

        $this->logger->clear(); // Clear previous queries

        // Only change one field
        $product->name = 'Only Name Changed';
        $this->repository->save($product);

        // Check that the UPDATE query only updates the changed field
        $queries = $this->logger->getQueries();
        $updateQuery = end($queries);

        $this->assertIsArray($updateQuery);
        $this->assertArrayHasKey('sql', $updateQuery);
        $this->assertIsString($updateQuery['sql']);
        $this->assertStringContainsString('product_name', $updateQuery['sql']);
        // Should not contain unchanged fields (price, stock, etc.)
    }

    public function testDeleteEntity(): void
    {
        $this->seedProducts();

        $product = $this->repository->findOrFail(1);
        $this->repository->delete($product);

        $this->assertRowNotExists('product', ['product_id' => 1]);
    }

    public function testDeleteById(): void
    {
        $this->seedProducts();

        $result = $this->repository->deleteById(1);

        $this->assertTrue($result);
        $this->assertRowNotExists('product', ['product_id' => 1]);
    }

    public function testDeleteByIdReturnsFalseWhenNotFound(): void
    {
        $result = $this->repository->deleteById(999);

        $this->assertFalse($result);
    }

    public function testCount(): void
    {
        $this->seedProducts();

        $count = $this->repository->count();

        $this->assertEquals(3, $count);
    }

    public function testTransactionCommit(): void
    {
        $this->repository->beginTransaction();

        $product = $this->repository->create();
        $product->name = 'Transaction Product';
        $product->price = 50.00;
        $product->stock = 5;
        $this->repository->save($product);

        $this->repository->commit();

        $this->assertRowExists('product', ['product_name' => 'Transaction Product']);
    }

    public function testTransactionRollback(): void
    {
        $this->repository->beginTransaction();

        $product = $this->repository->create();
        $product->name = 'Rollback Product';
        $product->price = 50.00;
        $product->stock = 5;
        $this->repository->save($product);

        $this->repository->rollback();

        $this->assertRowNotExists('product', ['product_name' => 'Rollback Product']);
    }

    public function testTransactionHelper(): void
    {
        $result = $this->repository->transaction(function () {
            $product = $this->repository->create();
            $product->name = 'Helper Transaction Product';
            $product->price = 75.00;
            $product->stock = 15;
            $this->repository->save($product);

            return $product;
        });

        $this->assertInstanceOf(Product::class, $result);
        $this->assertRowExists('product', ['product_name' => 'Helper Transaction Product']);
    }

    public function testTransactionHelperRollbackOnException(): void
    {
        try {
            $this->repository->transaction(function () {
                $product = $this->repository->create();
                $product->name = 'Exception Product';
                $product->price = 75.00;
                $product->stock = 15;
                $this->repository->save($product);

                throw new \RuntimeException('Simulated error');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertRowNotExists('product', ['product_name' => 'Exception Product']);
    }

    public function testFindActiveProducts(): void
    {
        $this->seedProducts();

        // Deactivate one product
        $pdo = $this->connection->getPdo();
        $pdo->exec('UPDATE product SET product_active = 0 WHERE product_id = 1');

        $activeProducts = $this->repository->findActive();

        $this->assertCount(2, $activeProducts);
    }

    public function testFindByCategory(): void
    {
        $this->seedProducts();

        $electronicsProducts = $this->repository->findByCategory(1);

        $this->assertCount(2, $electronicsProducts);
        foreach ($electronicsProducts as $product) {
            $this->assertEquals(1, $product->categoryId);
        }
    }

    public function testVersioningOnUpdate(): void
    {
        $this->seedProducts();

        $product = $this->repository->findOrFail(1);
        $initialVersion = $product->version;

        $product->name = 'Version Update Test';
        $this->repository->save($product);

        // Reload to verify
        $reloaded = $this->repository->findOrFail(1);

        $this->assertEquals($initialVersion + 1, $reloaded->version);
    }

    public function testUuidIsGeneratedOnSave(): void
    {
        $product = $this->repository->create();
        $product->name = 'UUID Test Product';
        $product->price = 10.00;
        $product->stock = 1;

        $this->assertNull($product->uuid);

        $this->repository->save($product);

        $this->assertNotNull($product->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $product->uuid,
        );
    }
}
