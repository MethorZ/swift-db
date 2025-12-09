<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Entity;

use PHPUnit\Framework\TestCase;

final class AbstractEntityTest extends TestCase
{
    public function testGetTableName(): void
    {
        $this->assertEquals('product', TestProduct::getTableName());
    }

    public function testGetPrimaryKeyColumn(): void
    {
        $this->assertEquals('product_id', TestProduct::getPrimaryKeyColumn());
    }

    public function testGetColumnMapping(): void
    {
        $entity = new TestProduct();
        $mapping = $entity->getColumnMapping();

        $this->assertArrayHasKey('id', $mapping);
        $this->assertArrayHasKey('name', $mapping);
        $this->assertArrayHasKey('price', $mapping);
        $this->assertArrayHasKey('updatedAt', $mapping);
        $this->assertArrayHasKey('createdAt', $mapping);
        $this->assertArrayHasKey('uuid', $mapping);

        $this->assertEquals('product_id', $mapping['id']);
        $this->assertEquals('product_name', $mapping['name']);
        $this->assertEquals('product_price', $mapping['price']);
    }

    public function testHydrateAndExtract(): void
    {
        $entity = new TestProduct();
        $entity->hydrate([
            'product_id' => 1,
            'product_name' => 'Test Widget',
            'product_price' => 29.99,
        ]);

        $this->assertEquals(1, $entity->id);
        $this->assertEquals('Test Widget', $entity->name);
        $this->assertEquals(29.99, $entity->price);
        $this->assertTrue($entity->isPersisted());

        $extracted = $entity->extract();
        $this->assertEquals(1, $extracted['product_id']);
        $this->assertEquals('Test Widget', $extracted['product_name']);
        $this->assertEquals(29.99, $extracted['product_price']);
    }

    public function testDirtyTracking(): void
    {
        $entity = new TestProduct();
        $entity->hydrate([
            'product_id' => 1,
            'product_name' => 'Original',
            'product_price' => 10.0,
        ]);

        $this->assertFalse($entity->isDirty());

        $entity->name = 'Modified';
        $this->assertTrue($entity->isDirty());

        $dirty = $entity->getDirtyFields();
        $this->assertArrayHasKey('product_name', $dirty);
        $this->assertEquals('Modified', $dirty['product_name']);

        $entity->markClean();
        $this->assertFalse($entity->isDirty());
    }

    public function testUuidTrait(): void
    {
        $entity = new TestProduct();
        $this->assertNull($entity->uuid);

        $entity->generateUuid();
        $this->assertNotNull($entity->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $entity->uuid,
        );
    }

    public function testTimestampsTrait(): void
    {
        $entity = new TestProduct();
        $this->assertNull($entity->createdAt);
        $this->assertNull($entity->updatedAt);

        $entity->touchTimestamps();
        $this->assertNotNull($entity->createdAt);
        $this->assertNotNull($entity->updatedAt);

        $oldCreated = $entity->createdAt;
        $entity->touchTimestamps();
        $this->assertEquals($oldCreated, $entity->createdAt); // Created doesn't change
    }

    public function testFromArray(): void
    {
        $entity = TestProduct::fromArray([
            'product_id' => 5,
            'product_name' => 'From Array',
            'product_price' => 99.99,
        ]);

        $this->assertEquals(5, $entity->id);
        $this->assertEquals('From Array', $entity->name);
        $this->assertEquals(99.99, $entity->price);
    }

    public function testToArray(): void
    {
        $entity = new TestProduct();
        $entity->id = 10;
        $entity->name = 'Test';
        $entity->price = 5.50;

        $array = $entity->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertEquals(10, $array['id']);
        $this->assertEquals('Test', $array['name']);
        $this->assertEquals(5.50, $array['price']);
    }
}
