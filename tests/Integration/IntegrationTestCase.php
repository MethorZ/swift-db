<?php

declare(strict_types=1);

namespace MethorZ\SwiftDb\Tests\Integration;

use MethorZ\SwiftDb\Connection\Connection;
use MethorZ\SwiftDb\Connection\ConnectionManager;
use MethorZ\SwiftDb\Query\QueryLogger;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests
 *
 * Provides database connection and helper methods for tests
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?ConnectionManager $connectionManager = null;

    protected static ?QueryLogger $queryLogger = null;

    protected Connection $connection;

    protected QueryLogger $logger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create connection manager
        self::$connectionManager = new ConnectionManager([
            'default' => 'default',
            'connections' => [
                'default' => [
                    'dsn' => sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        getenv('DB_HOST') ?: '127.0.0.1',
                        getenv('DB_PORT') ?: '33066',
                        getenv('DB_DATABASE') ?: 'methorz_test',
                    ),
                    'username' => getenv('DB_USERNAME') ?: 'methorz',
                    'password' => getenv('DB_PASSWORD') ?: 'methorz',
                ],
            ],
        ]);

        self::$queryLogger = new QueryLogger();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$connectionManager === null || self::$queryLogger === null) {
            $this->markTestSkipped('Database connection not available');
        }

        $this->connection = self::$connectionManager->getConnection();
        $this->logger = self::$queryLogger;

        // Clear query log before each test
        $this->logger->clear();

        // Start each test with a clean slate
        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Rollback any uncommitted transactions
        if ($this->connection->inTransaction()) {
            $this->connection->rollback();
        }
    }

    /**
     * Truncate all test tables
     */
    protected function truncateTables(): void
    {
        $tables = ['product', 'category', 'user', '`order`'];

        $pdo = $this->connection->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insert seed data for tests
     */
    protected function seedCategories(): void
    {
        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO category (category_name) VALUES
            ('Electronics'),
            ('Clothing'),
            ('Books')
        ");
    }

    /**
     * Insert seed products
     */
    protected function seedProducts(): void
    {
        $this->seedCategories();

        $pdo = $this->connection->getPdo();
        $pdo->exec("
            INSERT INTO product
                (product_uuid, product_name, product_description,
                 product_price, product_stock, product_category_id)
            VALUES
                ('11111111-1111-1111-1111-111111111111', 'Test Product 1',
                 'Description 1', 19.99, 100, 1),
                ('22222222-2222-2222-2222-222222222222', 'Test Product 2',
                 'Description 2', 29.99, 50, 1),
                ('33333333-3333-3333-3333-333333333333', 'Test Product 3',
                 'Description 3', 39.99, 0, 2)
        ");
    }

    /**
     * Get the number of queries executed in the current test
     */
    protected function getQueryCount(): int
    {
        return $this->logger->getQueryCount();
    }

    /**
     * Assert that a specific number of queries were executed
     */
    protected function assertQueryCount(int $expected, string $message = ''): void
    {
        $this->assertEquals($expected, $this->getQueryCount(), $message);
    }

    /**
     * Assert a row exists in a table
     *
     * @param array<string, mixed> $criteria
     */
    protected function assertRowExists(string $table, array $criteria, string $message = ''): void
    {
        $pdo = $this->connection->getPdo();

        $wheres = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            $wheres[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, implode(' AND ', $wheres));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, $message ?: "Row not found in {$table}");
    }

    /**
     * Assert a row does not exist in a table
     *
     * @param array<string, mixed> $criteria
     */
    protected function assertRowNotExists(string $table, array $criteria, string $message = ''): void
    {
        $pdo = $this->connection->getPdo();

        $wheres = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            $wheres[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, implode(' AND ', $wheres));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, $message ?: "Row unexpectedly found in {$table}");
    }

    /**
     * Get count of rows in a table
     */
    protected function getTableCount(string $table): int
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");

        if ($stmt === false) {
            return 0;
        }

        $result = $stmt->fetchColumn();

        return is_numeric($result) ? (int) $result : 0;
    }
}
