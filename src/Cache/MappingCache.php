<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Cache;

use MethorZ\SwiftDb\Entity\EntityInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * OPcache-friendly cache for entity column mappings
 *
 * Uses file-based caching that leverages PHP's OPcache for optimal performance.
 * On first access, builds the mapping via reflection and stores as a PHP file.
 * Subsequent accesses load the cached PHP file (from OPcache if enabled).
 */
final class MappingCache
{
    /**
     * Runtime cache for current request
     *
     * @var array<string, array<string, array{property: string, column: string, type: string|null, nullable: bool}>>
     */
    private static array $runtimeCache = [];

    public function __construct(
        private readonly ?string $cacheDir = null,
    ) {
    }

    /**
     * Get the column mapping for an entity class
     *
     * @param class-string<EntityInterface> $entityClass
     *
     * @return array<string, array{property: string, column: string, type: string|null, nullable: bool}>
     */
    public function getMapping(string $entityClass): array
    {
        // Level 1: Runtime cache
        if (isset(self::$runtimeCache[$entityClass])) {
            return self::$runtimeCache[$entityClass];
        }

        // Level 2: File cache (if cache directory is configured)
        if ($this->cacheDir !== null) {
            $cacheFile = $this->getCacheFile($entityClass);

            if (file_exists($cacheFile)) {
                /** @var array<string, array{property: string, column: string, type: string|null, nullable: bool}> $cached */
                $cached = require $cacheFile;
                self::$runtimeCache[$entityClass] = $cached;

                return $cached;
            }
        }

        // Level 3: Build mapping
        $mapping = $this->buildMapping($entityClass);

        // Store in file cache
        if ($this->cacheDir !== null) {
            $this->writeCache($entityClass, $mapping);
        }

        self::$runtimeCache[$entityClass] = $mapping;

        return $mapping;
    }

    /**
     * Build the mapping for an entity class using reflection
     *
     * @param class-string<EntityInterface> $entityClass
     *
     * @return array<string, array{property: string, column: string, type: string|null, nullable: bool}>
     */
    private function buildMapping(string $entityClass): array
    {
        $entity = new $entityClass();
        $columnMapping = $entity->getColumnMapping();
        $reflection = new ReflectionClass($entityClass);

        $mapping = [];
        foreach ($columnMapping as $property => $column) {
            $propertyType = null;
            $nullable = true;

            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                $type = $reflectionProperty->getType();

                if ($type instanceof ReflectionNamedType) {
                    $propertyType = $type->getName();
                    $nullable = $type->allowsNull();
                }
            }

            $mapping[$column] = [
                'property' => $property,
                'column' => $column,
                'type' => $propertyType,
                'nullable' => $nullable,
            ];
        }

        return $mapping;
    }

    /**
     * Write the mapping to cache file
     *
     * @param class-string $entityClass
     * @param array<string, array{property: string, column: string, type: string|null, nullable: bool}> $mapping
     */
    private function writeCache(string $entityClass, array $mapping): void
    {
        $cacheFile = $this->getCacheFile($entityClass);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $code = '<?php return ' . var_export($mapping, true) . ';';
        file_put_contents($cacheFile, $code);

        // Invalidate OPcache for this file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
        }
    }

    /**
     * Get the cache file path for an entity class
     */
    private function getCacheFile(string $entityClass): string
    {
        return $this->cacheDir . '/' . md5($entityClass) . '.php';
    }

    /**
     * Clear all cached mappings
     */
    public function clear(): void
    {
        self::$runtimeCache = [];

        if ($this->cacheDir === null || !is_dir($this->cacheDir)) {
            return;
        }

        $files = glob($this->cacheDir . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            unlink($file);

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        }
    }

    /**
     * Clear the cache for a specific entity class
     *
     * @param class-string $entityClass
     */
    public function clearFor(string $entityClass): void
    {
        unset(self::$runtimeCache[$entityClass]);

        if ($this->cacheDir === null) {
            return;
        }

        $cacheFile = $this->getCacheFile($entityClass);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($cacheFile, true);
            }
        }
    }
}
