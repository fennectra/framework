<?php

namespace Tests\Unit;

use Fennec\Core\Database\DatabaseDriverInterface;
use Fennec\Core\Database\DriverFactory;
use Fennec\Core\Database\MySQLDriver;
use Fennec\Core\Database\PostgreSQLDriver;
use Fennec\Core\Database\SQLiteDriver;
use PHPUnit\Framework\TestCase;

class DatabaseDriverTest extends TestCase
{
    // ── PostgreSQL ──────────────────────────

    public function testPostgresDriverBuildsDsn(): void
    {
        $driver = new PostgreSQLDriver();
        $dsn = $driver->buildDsn(['host' => '127.0.0.1', 'port' => '5432', 'db' => 'mydb']);

        $this->assertSame('pgsql:host=127.0.0.1;port=5432;dbname=mydb', $dsn);
    }

    public function testPostgresDriverDefaults(): void
    {
        $driver = new PostgreSQLDriver();

        $this->assertSame('pgsql', $driver->getName());
        $this->assertSame('5432', $driver->getDefaultPort());
        $this->assertSame('localhost', $driver->getDefaultHost());
        $this->assertSame('POSTGRES', $driver->getEnvPrefix());
    }

    public function testPostgresDriverMigrationsTableSql(): void
    {
        $driver = new PostgreSQLDriver();
        $sql = $driver->getMigrationsTableSql();

        $this->assertStringContainsString('SERIAL PRIMARY KEY', $sql);
        $this->assertStringContainsString('migrations', $sql);
    }

    public function testPostgresDriverUsesDefaultHostAndPort(): void
    {
        $driver = new PostgreSQLDriver();
        $dsn = $driver->buildDsn(['db' => 'testdb']);

        $this->assertSame('pgsql:host=localhost;port=5432;dbname=testdb', $dsn);
    }

    // ── MySQL ──────────────────────────

    public function testMysqlDriverBuildsDsn(): void
    {
        $driver = new MySQLDriver();
        $dsn = $driver->buildDsn(['host' => '10.0.0.1', 'port' => '3306', 'db' => 'app']);

        $this->assertSame('mysql:host=10.0.0.1;port=3306;dbname=app;charset=utf8mb4', $dsn);
    }

    public function testMysqlDriverDefaults(): void
    {
        $driver = new MySQLDriver();

        $this->assertSame('mysql', $driver->getName());
        $this->assertSame('3306', $driver->getDefaultPort());
        $this->assertSame('localhost', $driver->getDefaultHost());
        $this->assertSame('MYSQL', $driver->getEnvPrefix());
    }

    public function testMysqlDriverMigrationsTableSql(): void
    {
        $driver = new MySQLDriver();
        $sql = $driver->getMigrationsTableSql();

        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('utf8mb4', $sql);
        $this->assertStringContainsString('InnoDB', $sql);
    }

    public function testMysqlDriverPdoOptions(): void
    {
        $driver = new MySQLDriver();
        $options = $driver->getPdoOptions();

        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertStringContainsString('utf8mb4', $options[\PDO::MYSQL_ATTR_INIT_COMMAND]);
    }

    // ── SQLite ──────────────────────────

    public function testSqliteDriverBuildsDsnWithFile(): void
    {
        $driver = new SQLiteDriver();
        $dsn = $driver->buildDsn(['db' => '/tmp/test.sqlite']);

        $this->assertSame('sqlite:/tmp/test.sqlite', $dsn);
    }

    public function testSqliteDriverBuildsDsnMemory(): void
    {
        $driver = new SQLiteDriver();
        $dsn = $driver->buildDsn(['db' => ':memory:']);

        $this->assertSame('sqlite::memory:', $dsn);
    }

    public function testSqliteDriverDefaults(): void
    {
        $driver = new SQLiteDriver();

        $this->assertSame('sqlite', $driver->getName());
        $this->assertSame('', $driver->getDefaultPort());
        $this->assertSame('', $driver->getDefaultHost());
        $this->assertSame('SQLITE', $driver->getEnvPrefix());
    }

    public function testSqliteDriverMigrationsTableSql(): void
    {
        $driver = new SQLiteDriver();
        $sql = $driver->getMigrationsTableSql();

        $this->assertStringContainsString('AUTOINCREMENT', $sql);
        $this->assertStringContainsString('INTEGER PRIMARY KEY', $sql);
    }

    public function testSqliteDriverEmptyPdoOptions(): void
    {
        $driver = new SQLiteDriver();

        $this->assertSame([], $driver->getPdoOptions());
    }

    // ── DriverFactory ──────────────────────────

    public function testFactoryCreatesPgsqlDriver(): void
    {
        $driver = DriverFactory::make('pgsql');

        $this->assertInstanceOf(PostgreSQLDriver::class, $driver);
    }

    public function testFactoryCreatesMysqlDriver(): void
    {
        $driver = DriverFactory::make('mysql');

        $this->assertInstanceOf(MySQLDriver::class, $driver);
    }

    public function testFactoryCreatesSqliteDriver(): void
    {
        $driver = DriverFactory::make('sqlite');

        $this->assertInstanceOf(SQLiteDriver::class, $driver);
    }

    public function testFactoryThrowsOnUnsupportedDriver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: oracle');

        DriverFactory::make('oracle');
    }

    public function testFactorySupportedDrivers(): void
    {
        $supported = DriverFactory::supported();

        $this->assertContains('pgsql', $supported);
        $this->assertContains('mysql', $supported);
        $this->assertContains('sqlite', $supported);
    }

    public function testFactoryRegisterCustomDriver(): void
    {
        DriverFactory::register('custom', PostgreSQLDriver::class);

        $driver = DriverFactory::make('custom');

        $this->assertInstanceOf(PostgreSQLDriver::class, $driver);
    }

    // ── Interface contract ──────────────────────────

    /**
     * @dataProvider driverProvider
     */
    public function testAllDriversImplementInterface(string $driverName): void
    {
        $driver = DriverFactory::make($driverName);

        $this->assertInstanceOf(DatabaseDriverInterface::class, $driver);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testAllDriversReturnNonEmptyName(string $driverName): void
    {
        $driver = DriverFactory::make($driverName);

        $this->assertNotEmpty($driver->getName());
    }

    /**
     * @dataProvider driverProvider
     */
    public function testAllDriversMigrationsTableSqlContainsCreateTable(string $driverName): void
    {
        $driver = DriverFactory::make($driverName);
        $sql = $driver->getMigrationsTableSql();

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('migrations', $sql);
        $this->assertStringContainsString('batch', $sql);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function driverProvider(): array
    {
        return [
            'pgsql' => ['pgsql'],
            'mysql' => ['mysql'],
            'sqlite' => ['sqlite'],
        ];
    }
}
