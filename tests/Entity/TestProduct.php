<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Entity;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\TimestampsTrait;
use MethorZ\SwiftDb\Trait\UuidTrait;

/**
 * Test entity for testing
 */
final class TestProduct extends AbstractEntity
{
    use TimestampsTrait;
    use UuidTrait;

    public ?int $id = null;

    public string $name = '';

    public float $price = 0.0;

    public static function getTableName(): string
    {
        return 'product';
    }

    public function getColumnMapping(): array
    {
        return [
            'id' => 'product_id',
            'name' => 'product_name',
            'price' => 'product_price',
            ...$this->getTimestampMapping(),
            ...$this->getUuidMapping(),
        ];
    }
}
