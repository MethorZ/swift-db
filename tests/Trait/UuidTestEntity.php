<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Trait;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\UuidTrait;

/**
 * Test entity using UuidTrait
 */
class UuidTestEntity extends AbstractEntity
{
    use UuidTrait;

    public ?int $id = null;

    public static function getTableName(): string
    {
        return 'test_entity';
    }

    /**
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return array_merge(
            ['id' => 'test_entity_id'],
            $this->getUuidMapping(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getPublicUuidMapping(): array
    {
        return $this->getUuidMapping();
    }
}
