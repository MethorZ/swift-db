<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Trait;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimestampsTraitTest extends TestCase
{
    // =========================================================================
    // INITIAL STATE TESTS
    // =========================================================================

    public function testInitialTimestampsAreNull(): void
    {
        $entity = new TimestampsTestEntity();

        $this->assertNull($entity->updatedAt);
        $this->assertNull($entity->createdAt);
    }

    // =========================================================================
    // TOUCH UPDATED TESTS
    // =========================================================================

    public function testTouchUpdatedSetsCurrentTime(): void
    {
        $entity = new TimestampsTestEntity();
        $before = new DateTimeImmutable();

        $entity->touchUpdated();

        $this->assertNotNull($entity->updatedAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->updatedAt);
        $this->assertGreaterThanOrEqual($before, $entity->updatedAt);
    }

    public function testTouchUpdatedOverwritesExistingValue(): void
    {
        $entity = new TimestampsTestEntity();
        $entity->updatedAt = new DateTimeImmutable('2020-01-01');

        usleep(1000); // Small delay to ensure different timestamp
        $entity->touchUpdated();

        $this->assertNotNull($entity->updatedAt);
        $this->assertGreaterThan(new DateTimeImmutable('2020-01-01'), $entity->updatedAt);
    }

    // =========================================================================
    // TOUCH CREATED TESTS
    // =========================================================================

    public function testTouchCreatedSetsCurrentTime(): void
    {
        $entity = new TimestampsTestEntity();
        $before = new DateTimeImmutable();

        $entity->touchCreated();

        $this->assertNotNull($entity->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->createdAt);
        $this->assertGreaterThanOrEqual($before, $entity->createdAt);
    }

    public function testTouchCreatedDoesNotOverwriteExistingValue(): void
    {
        $entity = new TimestampsTestEntity();
        $originalTime = new DateTimeImmutable('2020-01-01');
        $entity->createdAt = $originalTime;

        $entity->touchCreated();

        $this->assertEquals($originalTime, $entity->createdAt);
    }

    // =========================================================================
    // TOUCH TIMESTAMPS (BOTH) TESTS
    // =========================================================================

    public function testTouchTimestampsSetsUpdatedTime(): void
    {
        $entity = new TimestampsTestEntity();
        $before = new DateTimeImmutable();

        $entity->touchTimestamps();

        $this->assertNotNull($entity->updatedAt);
        $this->assertGreaterThanOrEqual($before, $entity->updatedAt);
    }

    public function testTouchTimestampsSetsCreatedTimeWhenNull(): void
    {
        $entity = new TimestampsTestEntity();
        $before = new DateTimeImmutable();

        $entity->touchTimestamps();

        $this->assertNotNull($entity->createdAt);
        $this->assertGreaterThanOrEqual($before, $entity->createdAt);
    }

    public function testTouchTimestampsDoesNotOverwriteCreatedTime(): void
    {
        $entity = new TimestampsTestEntity();
        $originalTime = new DateTimeImmutable('2020-01-01');
        $entity->createdAt = $originalTime;

        $entity->touchTimestamps();

        $this->assertEquals($originalTime, $entity->createdAt);
        $this->assertNotNull($entity->updatedAt);
        $this->assertGreaterThan($originalTime, $entity->updatedAt);
    }

    public function testTouchTimestampsSetsSameTimeForBothWhenCreatedIsNull(): void
    {
        $entity = new TimestampsTestEntity();

        $entity->touchTimestamps();

        $this->assertEquals($entity->createdAt, $entity->updatedAt);
    }

    // =========================================================================
    // COLUMN NAME TESTS
    // =========================================================================

    public function testGetTimestampColumnsUsesTablePrefix(): void
    {
        $entity = new TimestampsTestEntity();

        $columns = $entity->getTimestampColumns();

        $this->assertEquals('test_entity_updated', $columns['updated']);
        $this->assertEquals('test_entity_created', $columns['created']);
    }

    public function testGetTimestampMappingReturnsCorrectMapping(): void
    {
        $entity = new TimestampsTestEntity();

        $mapping = $entity->getPublicTimestampMapping();

        $this->assertEquals('test_entity_updated', $mapping['updatedAt']);
        $this->assertEquals('test_entity_created', $mapping['createdAt']);
    }

    // =========================================================================
    // INTEGRATION WITH OTHER VALUES TESTS
    // =========================================================================

    public function testTimestampsAreDateTimeImmutable(): void
    {
        $entity = new TimestampsTestEntity();

        $entity->touchTimestamps();

        $this->assertInstanceOf(DateTimeImmutable::class, $entity->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->updatedAt);

        // Verify they are truly immutable (modifying should not affect original)
        $this->assertNotNull($entity->createdAt);
        $original = $entity->createdAt;
        $modified = $original->modify('+1 day');

        $this->assertNotEquals($original, $modified);
        $this->assertEquals($original, $entity->createdAt);
    }

    public function testTimestampsCanBeSetManually(): void
    {
        $entity = new TimestampsTestEntity();
        $customDate = new DateTimeImmutable('2023-06-15 10:30:00');

        $entity->createdAt = $customDate;
        $entity->updatedAt = $customDate;

        $this->assertEquals($customDate, $entity->createdAt);
        $this->assertEquals($customDate, $entity->updatedAt);
    }
}
