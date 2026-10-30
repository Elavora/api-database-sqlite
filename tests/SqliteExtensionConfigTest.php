<?php

declare(strict_types=1);

use Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory;
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabaseSqlite\SqliteExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\DatabaseConnectionFactory;
use Elavora\Api\Framework\Contracts\TransactionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Regras de configuracao e de registro documentadas em docs/USO.md. */
final class SqliteExtensionConfigTest extends TestCase
{
    private const INVALID = 'A configuracao SQLite deve informar path ou memory=true.';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir() . '/elavora-sqlite-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->files[] = $path;

        return $path;
    }

    /** @param array<string, mixed> $config */
    private function factory(array $config): DatabaseConnectionFactory
    {
        $application = Application::create()->extend(new SqliteExtension($config));
        $factory = $application->container()->get(DatabaseConnectionFactory::class);
        self::assertInstanceOf(DatabaseConnectionFactory::class, $factory);

        return $factory;
    }

    public function testPathOpensFileDatabaseOnlyOnFirstConnection(): void
    {
        $path = $this->tempPath();
        $factory = $this->factory(['path' => $path]);

        self::assertFileDoesNotExist($path);

        $factory->connection()->exec('CREATE TABLE t (id INTEGER)');

        self::assertFileExists($path);
    }

    public function testFactoryIsPdoConnectionFactory(): void
    {
        self::assertInstanceOf(PdoConnectionFactory::class, $this->factory(['memory' => true]));
    }

    public function testMemoryTrueIgnoresPath(): void
    {
        $path = $this->tempPath();
        $this->factory(['memory' => true, 'path' => $path])->connection()->exec('CREATE TABLE t (id INTEGER)');

        self::assertFileDoesNotExist($path);
    }

    public function testUserDsnIsIgnored(): void
    {
        $path = $this->tempPath();
        $this->factory(['path' => $path, 'dsn' => 'sqlite::memory:'])->connection()->exec('CREATE TABLE t (id INTEGER)');

        self::assertFileExists($path);
    }

    public function testMemoryFalseFallsBackToPath(): void
    {
        $path = $this->tempPath();
        $this->factory(['memory' => false, 'path' => $path])->connection()->exec('CREATE TABLE t (id INTEGER)');

        self::assertFileExists($path);
    }

    public function testPathMemoryLiteralWorksAsInMemoryDatabase(): void
    {
        $factory = $this->factory(['path' => ':memory:']);

        self::assertSame(1, (int) $factory->connection()->query('SELECT 1')->fetchColumn());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidConfigs(): array
    {
        return [
            'vazio' => [[]],
            'path vazio' => [['path' => '']],
            'path numerico' => [['path' => 123]],
            'path null' => [['path' => null]],
            'path array' => [['path' => ['x']]],
            'memory 1' => [['memory' => 1]],
            'memory string' => [['memory' => 'true']],
            'memory false' => [['memory' => false]],
            'memory 1 com path vazio' => [['memory' => 1, 'path' => '']],
            'dsn sozinho' => [['dsn' => 'sqlite::memory:']],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidConfigs')]
    public function testInvalidSingleConfigThrowsOnRegister(array $config): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID);

        Application::create()->extend(new SqliteExtension($config));
    }

    public function testRegisterValidatesWithoutOpeningConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID);

        (new SqliteExtension([]))->register(Application::create());
    }

    public function testEmptyConnectionItemThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID);

        Application::create()->extend(new SqliteExtension(['connections' => ['a' => []]]));
    }

    public function testConnectionItemThatIsNotArrayThrowsWithName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuracao SQLite 'a' deve ser um array.");

        Application::create()->extend(new SqliteExtension(['connections' => ['a' => 'x.sqlite']]));
    }

    public function testInvalidItemAmongValidOnesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuracao SQLite 'bad' deve ser um array.");

        Application::create()->extend(new SqliteExtension([
            'connections' => ['default' => ['memory' => true], 'bad' => 5],
        ]));
    }

    public function testConnectionsIgnoresRootSingleKeys(): void
    {
        $factory = $this->factory(['path' => '', 'memory' => 1, 'connections' => ['a' => ['memory' => true]]]);

        self::assertSame(1, (int) $factory->connection('a')->query('SELECT 1')->fetchColumn());
    }

    public function testNullConnectionsCountsAsAbsent(): void
    {
        $factory = $this->factory(['connections' => null, 'memory' => true]);

        self::assertSame(1, (int) $factory->connection()->query('SELECT 1')->fetchColumn());
    }

    public function testNamedConnectionsAreCachedAndIndependent(): void
    {
        $factory = $this->factory(['connections' => [
            'default' => ['memory' => true],
            'analytics' => ['memory' => true],
        ]]);

        self::assertSame($factory->connection(), $factory->connection('default'));
        self::assertSame($factory->connection('analytics'), $factory->connection('analytics'));

        $factory->connection()->exec('CREATE TABLE only_default (id INTEGER)');
        $tables = $factory->connection('analytics')->query("SELECT name FROM sqlite_master WHERE name = 'only_default'");

        self::assertFalse($tables->fetchColumn());
    }

    public function testSingleFormatOnlyHasDefaultConnection(): void
    {
        $factory = $this->factory(['memory' => true]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conexao PDO 'other' nao esta configurada.");

        $factory->connection('other');
    }

    public function testConnectionsWithoutDefaultFailOnUnnamedConnection(): void
    {
        $factory = $this->factory(['connections' => ['a' => ['memory' => true]]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conexao PDO 'default' nao esta configurada.");

        $factory->connection();
    }

    public function testConnectionsWithoutDefaultFailToResolvePdoDatabase(): void
    {
        $application = Application::create()->extend(new SqliteExtension([
            'connections' => ['a' => ['memory' => true]],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Conexao PDO 'default' nao esta configurada.");

        $application->container()->get(PdoDatabase::class);
    }

    public function testPdoOptionsArePassedThrough(): void
    {
        $factory = $this->factory(['memory' => true, 'options' => [PDO::ATTR_CASE => PDO::CASE_UPPER]]);

        self::assertSame(PDO::CASE_UPPER, $factory->connection()->getAttribute(PDO::ATTR_CASE));
    }

    public function testPdoOptionsArePassedThroughPerNamedConnection(): void
    {
        $factory = $this->factory(['connections' => [
            'default' => ['memory' => true, 'options' => [PDO::ATTR_CASE => PDO::CASE_LOWER]],
            'upper' => ['memory' => true, 'options' => [PDO::ATTR_CASE => PDO::CASE_UPPER]],
        ]]);

        self::assertSame(PDO::CASE_LOWER, $factory->connection()->getAttribute(PDO::ATTR_CASE));
        self::assertSame(PDO::CASE_UPPER, $factory->connection('upper')->getAttribute(PDO::ATTR_CASE));
    }

    public function testPdoDatabaseUsesDefaultConnectionOfFactory(): void
    {
        $application = Application::create()->extend(new SqliteExtension(['connections' => [
            'default' => ['memory' => true],
            'other' => ['memory' => true],
        ]]));
        $factory = $application->container()->get(DatabaseConnectionFactory::class);
        $database = $application->container()->get(PdoDatabase::class);

        $database->execute('CREATE TABLE t (id INTEGER)');

        $exists = $factory->connection()->query("SELECT name FROM sqlite_master WHERE name = 't'")->fetchColumn();
        self::assertSame('t', $exists);
    }

    public function testManualTransactionsWorkThroughPdoDatabase(): void
    {
        $database = Application::create()->extend(new SqliteExtension(['memory' => true]))
            ->container()->get(PdoDatabase::class);
        $database->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

        $database->begin();
        $database->insert('t', ['name' => 'a']);
        $database->rollback();
        self::assertFalse($database->exists('SELECT 1 FROM t'));

        $database->begin();
        $database->insert('t', ['name' => 'b']);
        $database->commit();
        self::assertTrue($database->exists('SELECT 1 FROM t'));
    }

    public function testDoesNotRegisterTransactionManager(): void
    {
        $application = Application::create()->extend(new SqliteExtension(['memory' => true]));

        self::assertFalse($application->container()->has(TransactionManager::class));
    }
}
