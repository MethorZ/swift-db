<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Entity;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\VersionTrait;

/**
 * Test entity for testing VersionTrait
 */
final class TestVersionedProduct extends AbstractEntity
{
    use VersionTrait;

    public ?int $id = null;

    public string $name = '';

    public static function getTableName(): string
    {
        return 'versioned_product';
    }

    public function getColumnMapping(): array
    {
        return [
            'id' => 'versioned_product_id',
            'name' => 'versioned_product_name',
            ...$this->getVersionMapping(),
        ];
    }
}
