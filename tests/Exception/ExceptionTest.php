<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Exception;

use MethorZ\SwiftDb\Exception\ConnectionException;
use MethorZ\SwiftDb\Exception\DatabaseException;
use MethorZ\SwiftDb\Exception\DeadlockException;
use MethorZ\SwiftDb\Exception\DuplicateEntryException;
use MethorZ\SwiftDb\Exception\EntityException;
use MethorZ\SwiftDb\Exception\OptimisticLockException;
use MethorZ\SwiftDb\Exception\QueryException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    public function testConnectionExceptionFailedToConnect(): void
    {
        $exception = ConnectionException::failedToConnect(
            'mysql:host=localhost;dbname=test',
            'Access denied',
        );

        $this->assertInstanceOf(DatabaseException::class, $exception);
        $this->assertStringContainsString('mysql:host=localhost;dbname=test', $exception->getMessage());
        $this->assertStringContainsString('Access denied', $exception->getMessage());
    }

    public function testConnectionExceptionConnectionLost(): void
    {
        $exception = ConnectionException::connectionLost('Server has gone away');

        $this->assertStringContainsString('connection lost', strtolower($exception->getMessage()));
        $this->assertStringContainsString('Server has gone away', $exception->getMessage());
    }

    public function testConnectionExceptionInvalidConfiguration(): void
    {
        $exception = ConnectionException::invalidConfiguration('DSN is missing');

        $this->assertStringContainsString('Invalid database configuration', $exception->getMessage());
        $this->assertStringContainsString('DSN is missing', $exception->getMessage());
    }

    public function testQueryExceptionContainsSqlAndParams(): void
    {
        $exception = new QueryException(
            'Query failed',
            'SELECT * FROM users WHERE id = ?',
            [1],
        );

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $exception->getSql());
        $this->assertEquals([1], $exception->getParams());
    }

    public function testQueryExceptionExecutionFailed(): void
    {
        $exception = QueryException::executionFailed(
            'SELECT * FROM nonexistent',
            [],
            'Table does not exist',
        );

        $this->assertStringContainsString('Query execution failed', $exception->getMessage());
        $this->assertStringContainsString('Table does not exist', $exception->getMessage());
        $this->assertEquals('SELECT * FROM nonexistent', $exception->getSql());
    }

    public function testDeadlockExceptionIsDeadlock(): void
    {
        // Create a mock PDOException with deadlock error info
        $deadlockException = new \PDOException('Deadlock found');
        $reflection = new \ReflectionProperty($deadlockException, 'errorInfo');
        $reflection->setValue($deadlockException, ['40001', 1213, 'Deadlock found']);

        $this->assertTrue(DeadlockException::isDeadlock($deadlockException));

        // Non-deadlock exception
        $normalException = new \PDOException('Syntax error');
        $reflection->setValue($normalException, ['42000', 1064, 'Syntax error']);

        $this->assertFalse(DeadlockException::isDeadlock($normalException));
    }

    public function testDeadlockExceptionFromPdoException(): void
    {
        $pdoException = new \PDOException('Deadlock found');
        $exception = DeadlockException::fromPdoException(
            $pdoException,
            'UPDATE users SET name = ?',
            ['John'],
        );

        $this->assertInstanceOf(QueryException::class, $exception);
        $this->assertStringContainsString('Deadlock', $exception->getMessage());
        $this->assertEquals('UPDATE users SET name = ?', $exception->getSql());
    }

    public function testDuplicateEntryExceptionIsDuplicateEntry(): void
    {
        // Create a mock PDOException with duplicate entry error info
        $duplicateException = new \PDOException('Duplicate entry');
        $reflection = new \ReflectionProperty($duplicateException, 'errorInfo');
        $reflection->setValue($duplicateException, ['23000', 1062, 'Duplicate entry']);

        $this->assertTrue(DuplicateEntryException::isDuplicateEntry($duplicateException));

        // Non-duplicate exception
        $normalException = new \PDOException('Syntax error');
        $reflection->setValue($normalException, ['42000', 1064, 'Syntax error']);

        $this->assertFalse(DuplicateEntryException::isDuplicateEntry($normalException));
    }

    public function testEntityExceptionNotFound(): void
    {
        $exception = EntityException::notFound('App\\Entity\\User', 123);

        $this->assertStringContainsString('User', $exception->getMessage());
        $this->assertStringContainsString('123', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testEntityExceptionInvalidData(): void
    {
        $exception = EntityException::invalidData('App\\Entity\\User', 'Name cannot be empty');

        $this->assertStringContainsString('User', $exception->getMessage());
        $this->assertStringContainsString('Name cannot be empty', $exception->getMessage());
    }

    public function testEntityExceptionNotPersisted(): void
    {
        $exception = EntityException::notPersisted('App\\Entity\\User');

        $this->assertStringContainsString('User', $exception->getMessage());
        $this->assertStringContainsString('not been persisted', $exception->getMessage());
    }

    public function testOptimisticLockExceptionVersionMismatch(): void
    {
        $exception = OptimisticLockException::versionMismatch(
            'App\\Entity\\Product',
            42,
            3,
        );

        $this->assertStringContainsString('Product', $exception->getMessage());
        $this->assertStringContainsString('42', $exception->getMessage());
        $this->assertStringContainsString('version', strtolower($exception->getMessage()));
        $this->assertStringContainsString('3', $exception->getMessage());
    }
}
