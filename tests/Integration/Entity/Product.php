<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration\Entity;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\TimestampsTrait;
use MethorZ\SwiftDb\Trait\UuidTrait;
use MethorZ\SwiftDb\Trait\VersionTrait;

/**
 * Product entity for integration tests
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

    public static function getTableName(): string
    {
        return 'product';
    }

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
            ...$this->getTimestampMapping(),
            ...$this->getUuidMapping(),
            ...$this->getVersionMapping(),
        ];
    }
}
