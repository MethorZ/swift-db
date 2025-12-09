<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Query;

use MethorZ\SwiftDb\Query\QueryLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class QueryLoggerTest extends TestCase
{
    public function testLogAddsQuery(): void
    {
        $logger = new QueryLogger();

        $this->assertEquals(0, $logger->getQueryCount());

        $logger->log('SELECT * FROM users', [], 0.001);

        $this->assertEquals(1, $logger->getQueryCount());
    }

    public function testLogAddsMultipleQueries(): void
    {
        $logger = new QueryLogger();

        $logger->log('SELECT * FROM users', [], 0.001);
        $logger->log('SELECT * FROM products', [], 0.002);
        $logger->log('SELECT * FROM orders', [], 0.003);

        $this->assertEquals(3, $logger->getQueryCount());
    }

    public function testEnableDisable(): void
    {
        $logger = new QueryLogger();

        $this->assertTrue($logger->isEnabled());

        $logger->disable();
        $this->assertFalse($logger->isEnabled());

        $logger->log('SELECT 1', [], 0.001);
        $this->assertEquals(0, $logger->getQueryCount()); // Not logged

        $logger->enable();
        $this->assertTrue($logger->isEnabled());

        $logger->log('SELECT 1', [], 0.001);
        $this->assertEquals(1, $logger->getQueryCount()); // Logged
    }

    public function testGetQueries(): void
    {
        $logger = new QueryLogger();

        $logger->log('SELECT * FROM users WHERE id = ?', [1], 0.001);

        $queries = $logger->getQueries();

        $this->assertCount(1, $queries);
        $this->assertEquals('SELECT * FROM users WHERE id = ?', $queries[0]['sql']);
        $this->assertEquals([1], $queries[0]['params']);
        $this->assertEquals(0.001, $queries[0]['duration']);
    }

    public function testGetTotalTime(): void
    {
        $logger = new QueryLogger();

        $logger->log('SELECT 1', [], 0.001);
        $logger->log('SELECT 2', [], 0.002);
        $logger->log('SELECT 3', [], 0.003);

        $totalTime = $logger->getTotalTime();

        $this->assertEqualsWithDelta(0.006, $totalTime, 0.0001);
    }

    public function testGetSlowestQuery(): void
    {
        $logger = new QueryLogger();

        $logger->log('Fast query', [], 0.001);
        $logger->log('Slow query', [], 0.1);
        $logger->log('Medium query', [], 0.01);

        $slowest = $logger->getSlowestQuery();

        $this->assertNotNull($slowest);
        $this->assertEquals('Slow query', $slowest['sql']);
        $this->assertEquals(0.1, $slowest['duration']);
    }

    public function testGetSlowestQueryReturnsNullWhenEmpty(): void
    {
        $logger = new QueryLogger();

        $this->assertNull($logger->getSlowestQuery());
    }

    public function testGetSlowQueries(): void
    {
        $logger = new QueryLogger();

        $logger->log('Fast query', [], 0.001);
        $logger->log('Slow query 1', [], 1.5);
        $logger->log('Medium query', [], 0.5);
        $logger->log('Slow query 2', [], 2.0);

        $slowQueries = $logger->getSlowQueries(1.0); // Threshold: 1 second

        $this->assertCount(2, $slowQueries);
    }

    public function testClear(): void
    {
        $logger = new QueryLogger();

        $logger->log('Query 1', [], 0.001);
        $logger->log('Query 2', [], 0.002);

        $this->assertEquals(2, $logger->getQueryCount());

        $logger->clear();

        $this->assertEquals(0, $logger->getQueryCount());
    }

    public function testGetSummary(): void
    {
        $logger = new QueryLogger();

        $logger->log('Query 1', [], 0.001);
        $logger->log('Query 2', [], 0.002);
        $logger->log('Query 3', [], 0.003);

        $summary = $logger->getSummary();

        $this->assertEquals(3, $summary['count']);
        $this->assertEqualsWithDelta(6.0, $summary['total_time_ms'], 0.1);
        $this->assertEqualsWithDelta(2.0, $summary['avg_time_ms'], 0.1);
        $this->assertEqualsWithDelta(3.0, $summary['slowest_ms'], 0.1);
    }

    public function testGetSummaryWhenEmpty(): void
    {
        $logger = new QueryLogger();

        $summary = $logger->getSummary();

        $this->assertEquals(0, $summary['count']);
        $this->assertEquals(0.0, $summary['total_time_ms']);
        $this->assertEquals(0.0, $summary['avg_time_ms']);
        $this->assertNull($summary['slowest_ms']);
    }

    public function testConstructorWithPsrLogger(): void
    {
        $psrLogger = new NullLogger();
        $logger = new QueryLogger($psrLogger);

        // Should not throw, just verify it accepts a PSR logger
        $logger->log('SELECT 1', [], 0.001);

        $this->assertEquals(1, $logger->getQueryCount());
    }
}
