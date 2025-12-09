<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Cache;

use MethorZ\SwiftDb\Cache\IdentityMap;
use MethorZ\SwiftDb\Tests\Entity\TestProduct;
use PHPUnit\Framework\TestCase;

final class IdentityMapTest extends TestCase
{
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        $this->identityMap = new IdentityMap();
    }

    public function testGetReturnsNullWhenEntityNotInMap(): void
    {
        $result = $this->identityMap->get(TestProduct::class, 1);

        $this->assertNull($result);
    }

    public function testSetAndGetReturnsEntity(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;
        $entity->name = 'Test Product';

        $this->identityMap->set(TestProduct::class, 1, $entity);
        $result = $this->identityMap->get(TestProduct::class, 1);

        $this->assertSame($entity, $result);
    }

    public function testGetReturnsSameInstanceForSameId(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;
        $entity->name = 'Test Product';

        $this->identityMap->set(TestProduct::class, 1, $entity);

        $result1 = $this->identityMap->get(TestProduct::class, 1);
        $result2 = $this->identityMap->get(TestProduct::class, 1);

        $this->assertSame($result1, $result2);
    }

    public function testHasReturnsFalseWhenEntityNotInMap(): void
    {
        $result = $this->identityMap->has(TestProduct::class, 1);

        $this->assertFalse($result);
    }

    public function testHasReturnsTrueWhenEntityInMap(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity);
        $result = $this->identityMap->has(TestProduct::class, 1);

        $this->assertTrue($result);
    }

    public function testRemoveRemovesEntityFromMap(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity);
        $this->assertTrue($this->identityMap->has(TestProduct::class, 1));

        $this->identityMap->remove(TestProduct::class, 1);

        $this->assertFalse($this->identityMap->has(TestProduct::class, 1));
        $this->assertNull($this->identityMap->get(TestProduct::class, 1));
    }

    public function testClearClearsAllEntities(): void
    {
        $entity1 = new TestProduct();
        $entity1->id = 1;

        $entity2 = new TestProduct();
        $entity2->id = 2;

        $this->identityMap->set(TestProduct::class, 1, $entity1);
        $this->identityMap->set(TestProduct::class, 2, $entity2);

        $this->identityMap->clear();

        $this->assertFalse($this->identityMap->has(TestProduct::class, 1));
        $this->assertFalse($this->identityMap->has(TestProduct::class, 2));
        $this->assertEquals(0, $this->identityMap->count());
    }

    public function testClearClearsSpecificEntityClass(): void
    {
        $entity1 = new TestProduct();
        $entity1->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity1);

        $this->identityMap->clear(TestProduct::class);

        $this->assertFalse($this->identityMap->has(TestProduct::class, 1));
    }

    public function testCountReturnsZeroForEmptyMap(): void
    {
        $this->assertEquals(0, $this->identityMap->count());
    }

    public function testCountReturnsTotalEntities(): void
    {
        $entity1 = new TestProduct();
        $entity1->id = 1;

        $entity2 = new TestProduct();
        $entity2->id = 2;

        $this->identityMap->set(TestProduct::class, 1, $entity1);
        $this->identityMap->set(TestProduct::class, 2, $entity2);

        $this->assertEquals(2, $this->identityMap->count());
    }

    public function testCountReturnsCountForSpecificEntityClass(): void
    {
        $entity1 = new TestProduct();
        $entity1->id = 1;

        $entity2 = new TestProduct();
        $entity2->id = 2;

        $this->identityMap->set(TestProduct::class, 1, $entity1);
        $this->identityMap->set(TestProduct::class, 2, $entity2);

        $this->assertEquals(2, $this->identityMap->count(TestProduct::class));
    }

    public function testGetStatsReturnsStatistics(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity);

        // Miss
        $this->identityMap->get(TestProduct::class, 999);

        // Hit
        $this->identityMap->get(TestProduct::class, 1);

        $stats = $this->identityMap->getStats();

        $this->assertEquals(1, $stats['total']);
        $this->assertArrayHasKey(TestProduct::class, $stats['by_class']);
        $this->assertEquals(1, $stats['by_class'][TestProduct::class]);
        $this->assertEquals(1, $stats['hits'][TestProduct::class]);
        $this->assertEquals(1, $stats['misses'][TestProduct::class]);
    }

    public function testGetTracksHitCount(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity);

        // Multiple hits
        $this->identityMap->get(TestProduct::class, 1);
        $this->identityMap->get(TestProduct::class, 1);
        $this->identityMap->get(TestProduct::class, 1);

        $stats = $this->identityMap->getStats();

        $this->assertEquals(3, $stats['hits'][TestProduct::class]);
    }

    public function testGetTracksMissCount(): void
    {
        // Multiple misses
        $this->identityMap->get(TestProduct::class, 1);
        $this->identityMap->get(TestProduct::class, 2);
        $this->identityMap->get(TestProduct::class, 3);

        $stats = $this->identityMap->getStats();

        $this->assertEquals(3, $stats['misses'][TestProduct::class]);
    }

    public function testSupportsStringIds(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;
        $entity->name = 'String ID Product';

        $stringId = 'uuid-1234-5678-90ab';
        $this->identityMap->set(TestProduct::class, $stringId, $entity);

        $result = $this->identityMap->get(TestProduct::class, $stringId);

        $this->assertSame($entity, $result);
    }

    public function testClearResetsStatistics(): void
    {
        $entity = new TestProduct();
        $entity->id = 1;

        $this->identityMap->set(TestProduct::class, 1, $entity);
        $this->identityMap->get(TestProduct::class, 1);
        $this->identityMap->get(TestProduct::class, 999);

        $this->identityMap->clear();

        $stats = $this->identityMap->getStats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEmpty($stats['by_class']);
        $this->assertEmpty($stats['hits']);
        $this->assertEmpty($stats['misses']);
    }
}
