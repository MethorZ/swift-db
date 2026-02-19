<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Connection;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionConfig;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Connection::prepare() and Connection::query() methods
 *
 * Uses SQLite in-memory for real SQL execution testing.
 */
final class ConnectionPrepareQueryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $config = new ConnectionConfig(
            dsn: 'sqlite::memory:',
            username: '',
            password: '',
            options: [],
        );

        $this->connection = new Connection($config, 'test-sqlite');

        $this->connection->getPdo()->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT, value REAL)');
        $this->connection->getPdo()->exec("INSERT INTO test_items (name, value) VALUES ('alpha', 1.5)");
        $this->connection->getPdo()->exec("INSERT INTO test_items (name, value) VALUES ('beta', 2.5)");
        $this->connection->getPdo()->exec("INSERT INTO test_items (name, value) VALUES ('gamma', 3.5)");
    }

    // --- prepare() tests ---

    public function testPrepareReturnsPdoStatement(): void
    {
        $stmt = $this->connection->prepare('SELECT * FROM test_items WHERE id = ?');

        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function testPrepareWithPositionalParameters(): void
    {
        $stmt = $this->connection->prepare('SELECT name FROM test_items WHERE id = ?');
        $stmt->execute([1]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertSame('alpha', $result['name']);
    }

    public function testPrepareWithNamedParameters(): void
    {
        $stmt = $this->connection->prepare('SELECT name FROM test_items WHERE id = :id');
        $stmt->execute([':id' => 2]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertSame('beta', $result['name']);
    }

    public function testPrepareInsertStatement(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO test_items (name, value) VALUES (?, ?)');
        $stmt->execute(['delta', 4.5]);

        $this->assertSame(1, $stmt->rowCount());

        $verify = $this->connection->prepare('SELECT name FROM test_items WHERE id = ?');
        $verify->execute([4]);

        $result = $verify->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertSame('delta', $result['name']);
    }

    public function testPrepareUpdateStatement(): void
    {
        $stmt = $this->connection->prepare('UPDATE test_items SET value = ? WHERE name = ?');
        $stmt->execute([99.9, 'alpha']);

        $this->assertSame(1, $stmt->rowCount());
    }

    public function testPrepareDeleteStatement(): void
    {
        $stmt = $this->connection->prepare('DELETE FROM test_items WHERE id = ?');
        $stmt->execute([3]);

        $this->assertSame(1, $stmt->rowCount());
    }

    public function testPrepareThrowsOnInvalidSql(): void
    {
        $this->expectException(\PDOException::class);

        $this->connection->prepare('INVALID SQL STATEMENT %%%');
    }

    public function testPrepareReturnsReusableStatement(): void
    {
        $stmt = $this->connection->prepare('SELECT name FROM test_items WHERE id = ?');

        $stmt->execute([1]);
        $first = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt->execute([2]);
        $second = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertSame('alpha', $first['name']);
        $this->assertSame('beta', $second['name']);
    }

    // --- query() tests ---

    public function testQueryReturnsPdoStatement(): void
    {
        $stmt = $this->connection->query('SELECT * FROM test_items');

        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function testQueryReturnsAllRows(): void
    {
        $stmt = $this->connection->query('SELECT * FROM test_items ORDER BY id');

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);
        $this->assertSame('alpha', $rows[0]['name']);
        $this->assertSame('beta', $rows[1]['name']);
        $this->assertSame('gamma', $rows[2]['name']);
    }

    public function testQueryWithWhereClause(): void
    {
        $stmt = $this->connection->query("SELECT name FROM test_items WHERE value > 2.0 ORDER BY id");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows);
        $this->assertSame('beta', $rows[0]['name']);
        $this->assertSame('gamma', $rows[1]['name']);
    }

    public function testQueryCountAggregate(): void
    {
        $stmt = $this->connection->query('SELECT COUNT(*) as cnt FROM test_items');

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertEquals(3, $result['cnt']);
    }

    public function testQueryThrowsOnInvalidSql(): void
    {
        $this->expectException(\PDOException::class);

        $this->connection->query('SELECT * FROM nonexistent_table');
    }

    // --- execute() tests ---

    public function testExecuteInsertReturnsAffectedRows(): void
    {
        $affected = $this->connection->execute(
            'INSERT INTO test_items (name, value) VALUES (?, ?)',
            ['delta', 4.5],
        );

        $this->assertSame(1, $affected);
    }

    public function testExecuteUpdateReturnsAffectedRows(): void
    {
        $affected = $this->connection->execute(
            'UPDATE test_items SET value = ? WHERE name = ?',
            [99.9, 'alpha'],
        );

        $this->assertSame(1, $affected);
    }

    public function testExecuteDeleteReturnsAffectedRows(): void
    {
        $affected = $this->connection->execute(
            'DELETE FROM test_items WHERE id = ?',
            [1],
        );

        $this->assertSame(1, $affected);
    }

    public function testExecuteWithNoMatchReturnsZero(): void
    {
        $affected = $this->connection->execute(
            'DELETE FROM test_items WHERE id = ?',
            [999],
        );

        $this->assertSame(0, $affected);
    }

    public function testExecuteBulkUpdateReturnsMultipleAffected(): void
    {
        $affected = $this->connection->execute(
            'UPDATE test_items SET value = ?',
            [0.0],
        );

        $this->assertSame(3, $affected);
    }

    public function testExecuteThrowsOnInvalidSql(): void
    {
        $this->expectException(\PDOException::class);

        $this->connection->execute('INVALID SQL', []);
    }

    public function testExecuteWithNamedParameters(): void
    {
        $affected = $this->connection->execute(
            'UPDATE test_items SET value = :val WHERE name = :name',
            [':val' => 100.0, ':name' => 'beta'],
        );

        $this->assertSame(1, $affected);

        $stmt = $this->connection->query("SELECT value FROM test_items WHERE name = 'beta'");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertEquals(100.0, $result['value']);
    }

    // --- fetchOne() tests ---

    public function testFetchOneReturnsSingleRow(): void
    {
        $result = $this->connection->fetchOne(
            'SELECT name, value FROM test_items WHERE id = ?',
            [1],
        );

        $this->assertIsArray($result);
        $this->assertSame('alpha', $result['name']);
    }

    public function testFetchOneReturnsNullWhenNoMatch(): void
    {
        $result = $this->connection->fetchOne(
            'SELECT name FROM test_items WHERE id = ?',
            [999],
        );

        $this->assertNull($result);
    }

    public function testFetchOneReturnsFirstRowOnly(): void
    {
        $result = $this->connection->fetchOne(
            'SELECT name FROM test_items ORDER BY id',
            [],
        );

        $this->assertIsArray($result);
        $this->assertSame('alpha', $result['name']);
    }

    // --- fetchAll() tests ---

    public function testFetchAllReturnsAllMatchingRows(): void
    {
        $results = $this->connection->fetchAll(
            'SELECT name FROM test_items ORDER BY id',
            [],
        );

        $this->assertCount(3, $results);
        $this->assertSame('alpha', $results[0]['name']);
        $this->assertSame('gamma', $results[2]['name']);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoMatch(): void
    {
        $results = $this->connection->fetchAll(
            'SELECT name FROM test_items WHERE value > ?',
            [999.0],
        );

        $this->assertSame([], $results);
    }

    public function testFetchAllWithFilteredResults(): void
    {
        $results = $this->connection->fetchAll(
            'SELECT name FROM test_items WHERE value > ? ORDER BY value',
            [2.0],
        );

        $this->assertCount(2, $results);
        $this->assertSame('beta', $results[0]['name']);
        $this->assertSame('gamma', $results[1]['name']);
    }
}
