<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Exception;

/**
 * Exception thrown when a duplicate entry is detected
 */
final class DuplicateEntryException extends QueryException
{
    /**
     * MySQL duplicate entry error codes
     */
    private const DUPLICATE_ERROR_CODES = [
        1062, // ER_DUP_ENTRY
        1569, // ER_DUP_ENTRY_AUTOINCREMENT_CASE
        1586, // ER_DUP_ENTRY_WITH_KEY_NAME
    ];

    /**
     * @param array<mixed> $params
     */
    public static function fromPdoException(\PDOException $e, string $sql = '', array $params = []): self
    {
        return new self(
            sprintf('Duplicate entry detected: %s', $e->getMessage()),
            $sql,
            $params,
            (int) $e->getCode(),
            $e,
        );
    }

    public static function isDuplicateEntry(\PDOException $e): bool
    {
        $errorInfo = $e->errorInfo ?? [];

        return in_array($errorInfo[1] ?? 0, self::DUPLICATE_ERROR_CODES, true);
    }
}
