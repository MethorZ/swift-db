<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Query;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Query logger for debugging and monitoring
 */
final class QueryLogger
{
    /**
     * @var array<int, array{sql: string, params: array<mixed>, duration: float, time: float}>
     */
    private array $queries = [];

    private bool $enabled = true;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Enable query logging
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable query logging
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if logging is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Log a query execution
     *
     * @param array<mixed> $params
     */
    public function log(string $sql, array $params, float $duration): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'time' => microtime(true),
        ];

        $this->logger->debug('Query executed', [
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    /**
     * Get all logged queries
     *
     * @return array<int, array{sql: string, params: array<mixed>, duration: float, time: float}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get the total number of queries
     */
    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Get the total execution time of all queries
     */
    public function getTotalTime(): float
    {
        return array_sum(array_column($this->queries, 'duration'));
    }

    /**
     * Get the slowest query
     *
     * @return array{sql: string, params: array<mixed>, duration: float, time: float}|null
     */
    public function getSlowestQuery(): ?array
    {
        if (empty($this->queries)) {
            return null;
        }

        $slowest = null;
        foreach ($this->queries as $query) {
            if ($slowest === null || $query['duration'] > $slowest['duration']) {
                $slowest = $query;
            }
        }

        return $slowest;
    }

    /**
     * Get queries slower than the given threshold (in seconds)
     *
     * @return array<int, array{sql: string, params: array<mixed>, duration: float, time: float}>
     */
    public function getSlowQueries(float $thresholdSeconds = 1.0): array
    {
        return array_filter(
            $this->queries,
            fn (array $query) => $query['duration'] >= $thresholdSeconds,
        );
    }

    /**
     * Clear all logged queries
     */
    public function clear(): void
    {
        $this->queries = [];
    }

    /**
     * Get a summary of the logged queries
     *
     * @return array{count: int, total_time_ms: float, avg_time_ms: float, slowest_ms: float|null}
     */
    public function getSummary(): array
    {
        $count = $this->getQueryCount();
        $totalTime = $this->getTotalTime();
        $slowest = $this->getSlowestQuery();

        return [
            'count' => $count,
            'total_time_ms' => round($totalTime * 1000, 2),
            'avg_time_ms' => $count > 0 ? round(($totalTime / $count) * 1000, 2) : 0.0,
            'slowest_ms' => $slowest !== null ? round($slowest['duration'] * 1000, 2) : null,
        ];
    }
}
