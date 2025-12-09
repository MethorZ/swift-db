<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Trait;

use MethorZ\SwiftDb\Tests\Entity\TestVersionedProduct;
use PHPUnit\Framework\TestCase;

final class VersionTraitTest extends TestCase
{
    public function testVersionDefaultsToOne(): void
    {
        $entity = new TestVersionedProduct();

        $this->assertEquals(1, $entity->version);
    }

    public function testIncrementVersionIncreasesVersionByOne(): void
    {
        $entity = new TestVersionedProduct();
        $this->assertEquals(1, $entity->version);

        $entity->incrementVersion();
        $this->assertEquals(2, $entity->version);

        $entity->incrementVersion();
        $this->assertEquals(3, $entity->version);
    }

    public function testUsesOptimisticLockingReturnsTrue(): void
    {
        $entity = new TestVersionedProduct();

        $this->assertTrue($entity->usesOptimisticLocking());
    }

    public function testGetVersionColumnReturnsCorrectColumnName(): void
    {
        $entity = new TestVersionedProduct();

        $this->assertEquals('versioned_product_version', $entity->getVersionColumn());
    }

    public function testGetVersionMappingReturnsCorrectMapping(): void
    {
        $entity = new TestVersionedProduct();
        $mapping = $entity->getColumnMapping();

        $this->assertArrayHasKey('version', $mapping);
        $this->assertEquals('versioned_product_version', $mapping['version']);
    }

    public function testVersionIsIncludedInExtract(): void
    {
        $entity = new TestVersionedProduct();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->version = 5;

        $extracted = $entity->extract();

        $this->assertArrayHasKey('versioned_product_version', $extracted);
        $this->assertEquals(5, $extracted['versioned_product_version']);
    }

    public function testVersionCanBeHydrated(): void
    {
        $entity = new TestVersionedProduct();
        $entity->hydrate([
            'versioned_product_id' => 1,
            'versioned_product_name' => 'Test',
            'versioned_product_version' => 10,
        ]);

        $this->assertEquals(10, $entity->version);
    }
}
