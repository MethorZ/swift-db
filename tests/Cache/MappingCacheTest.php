<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Cache;

use MethorZ\SwiftDb\Cache\MappingCache;
use MethorZ\SwiftDb\Tests\Entity\TestProduct;
use PHPUnit\Framework\TestCase;

final class MappingCacheTest extends TestCase
{
    public function testGetMappingReturnsMapping(): void
    {
        $cache = new MappingCache();

        $mapping = $cache->getMapping(TestProduct::class);

        $this->assertArrayHasKey('product_id', $mapping);
        $this->assertArrayHasKey('product_name', $mapping);
        $this->assertArrayHasKey('product_price', $mapping);
    }

    public function testGetMappingReturnsCachedResult(): void
    {
        $cache = new MappingCache();

        $mapping1 = $cache->getMapping(TestProduct::class);
        $mapping2 = $cache->getMapping(TestProduct::class);

        // Should be the same array (from runtime cache)
        $this->assertSame($mapping1, $mapping2);
    }

    public function testMappingContainsPropertyInfo(): void
    {
        $cache = new MappingCache();

        $mapping = $cache->getMapping(TestProduct::class);

        // Check a specific column
        $this->assertArrayHasKey('product_name', $mapping);

        $nameMapping = $mapping['product_name'];
        $this->assertEquals('name', $nameMapping['property']);
        $this->assertEquals('product_name', $nameMapping['column']);
        $this->assertEquals('string', $nameMapping['type']);
        $this->assertFalse($nameMapping['nullable']);
    }

    public function testMappingHandlesNullableType(): void
    {
        $cache = new MappingCache();

        $mapping = $cache->getMapping(TestProduct::class);

        // Check nullable column (id can be null initially)
        $this->assertArrayHasKey('product_id', $mapping);

        $idMapping = $mapping['product_id'];
        $this->assertEquals('int', $idMapping['type']);
        $this->assertTrue($idMapping['nullable']);
    }

    public function testClearClearsRuntimeCache(): void
    {
        $cache = new MappingCache();

        // Get mapping to populate cache
        $cache->getMapping(TestProduct::class);

        // Clear cache
        $cache->clear();

        // Get mapping again - should work (rebuilds from reflection)
        $mapping = $cache->getMapping(TestProduct::class);

        $this->assertNotEmpty($mapping);
    }

    public function testClearForClearsSpecificEntity(): void
    {
        $cache = new MappingCache();

        // Get mapping to populate cache
        $cache->getMapping(TestProduct::class);

        // Clear cache for specific entity
        $cache->clearFor(TestProduct::class);

        // Get mapping again - should work (rebuilds from reflection)
        $mapping = $cache->getMapping(TestProduct::class);

        $this->assertNotEmpty($mapping);
    }

    public function testFileCacheWithDirectory(): void
    {
        $cacheDir = sys_get_temp_dir() . '/methorz-db-test-' . uniqid();

        // Create the directory first since the cache expects it to exist
        // or will be created during write
        $cache = new MappingCache($cacheDir);

        // Get mapping - should create cache file and directory
        $mapping = $cache->getMapping(TestProduct::class);

        $this->assertNotEmpty($mapping);

        // Cleanup
        $cache->clear();

        // Directory might exist if cache was written
        if (is_dir($cacheDir)) {
            rmdir($cacheDir);
        }
    }
}
