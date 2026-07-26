<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabaseSqlite\SqliteExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use Elavora\Api\Framework\Contracts\TransactionManager;
use PHPUnit\Framework\TestCase;

final class SqliteTransactionManagerTest extends TestCase
{
    public function testUsesDefaultMemoryConnectionAndRollsBack(): void
    {
        $application = Application::create()->extend(new SqliteExtension([
            'connections' => [
                'default' => ['memory' => true],
                'analytics' => ['memory' => true],
            ],
        ]));

        $factory = $application->container()->get(DatabaseConnectionFactory::class);
        $database = $application->container()->get(PdoDatabase::class);
        $transactionManager = $application->container()->get(TransactionManager::class);

        self::assertInstanceOf(DatabaseConnectionFactory::class, $factory);
        self::assertInstanceOf(PdoDatabase::class, $database);
        self::assertInstanceOf(TransactionManager::class, $transactionManager);
        self::assertSame($factory->connection(), $database->connection());
        self::assertSame($database, $transactionManager);
        self::assertNotSame($factory->connection(), $factory->connection('analytics'));

        self::assertFalse($factory->connection('analytics')->inTransaction());
        $this->assertRollback($database, $transactionManager);
    }

    public function testRollsBackFileDatabase(): void
    {
        $path = sys_get_temp_dir() . '/elavora-sqlite-' . bin2hex(random_bytes(8)) . '.sqlite';

        try {
            $application = Application::create()->extend(new SqliteExtension(['path' => $path]));
            $database = $application->container()->get(PdoDatabase::class);
            $transactionManager = $application->container()->get(TransactionManager::class);

            self::assertInstanceOf(PdoDatabase::class, $database);
            self::assertInstanceOf(TransactionManager::class, $transactionManager);

            $this->assertRollback($database, $transactionManager);
            $database->insert('transaction_probe', ['name' => 'persisted']);
            self::assertFileExists($path);

            $reopenedApplication = Application::create()->extend(
                new SqliteExtension(['path' => $path])
            );
            $reopenedDatabase = $reopenedApplication->container()->get(PdoDatabase::class);
            self::assertInstanceOf(PdoDatabase::class, $reopenedDatabase);
            self::assertSame(
                'persisted',
                $reopenedDatabase->value('SELECT name FROM transaction_probe')
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function assertRollback(
        PdoDatabase $database,
        TransactionManager $transactionManager
    ): void {
        $database->execute(
            'CREATE TABLE transaction_probe (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
        self::assertTrue($transactionManager->begin());
        self::assertTrue($database->connection()->inTransaction());
        $database->insert('transaction_probe', ['name' => 'rollback']);
        self::assertTrue($transactionManager->rollback());
        self::assertSame(0, (int) $database->value('SELECT COUNT(*) FROM transaction_probe'));
    }
}
