<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when a query fails
 */
class QueryException extends DatabaseException
{
    private string $sql;

    /**
     * @var array<mixed>
     */
    private array $params;

    /**
     * @param array<mixed> $params
     */
    public function __construct(
        string $message,
        string $sql = '',
        array $params = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->sql = $sql;
        $this->params = $params;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array<mixed> $params
     */
    public static function executionFailed(string $sql, array $params, string $reason): self
    {
        return new self(
            sprintf('Query execution failed: %s', $reason),
            $sql,
            $params,
        );
    }
}
