<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Examples\Entity;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\TimestampsTrait;
use MethorZ\SwiftDb\Trait\UuidTrait;
use MethorZ\SwiftDb\Trait\VersionTrait;

/**
 * Example Product entity demonstrating all package features
 *
 * Database table structure:
 * ```sql
 * CREATE TABLE product (
 *     product_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     product_uuid CHAR(36) NOT NULL,
 *     product_name VARCHAR(255) NOT NULL,
 *     product_description TEXT,
 *     product_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
 *     product_stock INT NOT NULL DEFAULT 0,
 *     product_category_id BIGINT UNSIGNED,
 *     product_active TINYINT(1) NOT NULL DEFAULT 1,
 *     product_version INT UNSIGNED NOT NULL DEFAULT 1,
 *     product_updated TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
 *     product_created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
 *     UNIQUE INDEX idx_uq_uuid (product_uuid),
 *     INDEX idx_category_id (product_category_id),
 *     INDEX idx_active (product_active),
 *     INDEX idx_created (product_created)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 * ```
 */
class Product extends AbstractEntity
{
    use TimestampsTrait;
    use UuidTrait;
    use VersionTrait;

    public ?int $id = null;

    public string $name = '';

    public ?string $description = null;

    public float $price = 0.0;

    public int $stock = 0;

    public ?int $categoryId = null;

    public bool $active = true;

    /**
     * Get the table name
     */
    public static function getTableName(): string
    {
        return 'product';
    }

    /**
     * Get column mapping (property name => database column name)
     *
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return [
            'id' => 'product_id',
            'name' => 'product_name',
            'description' => 'product_description',
            'price' => 'product_price',
            'stock' => 'product_stock',
            'categoryId' => 'product_category_id',
            'active' => 'product_active',
            // Include trait mappings
            ...$this->getTimestampMapping(),
            ...$this->getUuidMapping(),
            ...$this->getVersionMapping(),
        ];
    }

    /**
     * Check if the product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Reduce stock by given quantity
     */
    public function reduceStock(int $quantity): void
    {
        if ($quantity > $this->stock) {
            throw new \InvalidArgumentException('Cannot reduce stock below zero');
        }

        $this->stock -= $quantity;
    }

    /**
     * Increase stock by given quantity
     */
    public function addStock(int $quantity): void
    {
        $this->stock += $quantity;
    }

    /**
     * Activate the product
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Deactivate the product
     */
    public function deactivate(): void
    {
        $this->active = false;
    }
}
