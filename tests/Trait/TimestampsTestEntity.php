<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Trait;

use MethorZ\SwiftDb\Entity\AbstractEntity;
use MethorZ\SwiftDb\Trait\TimestampsTrait;

/**
 * Test entity using TimestampsTrait
 */
class TimestampsTestEntity extends AbstractEntity
{
    use TimestampsTrait;

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
            $this->getTimestampMapping(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getPublicTimestampMapping(): array
    {
        return $this->getTimestampMapping();
    }
}
