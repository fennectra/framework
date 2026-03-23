<?php

namespace Tests\Integration;

use Fennec\Core\DB;
use Fennec\Core\Env;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected static string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Get temp dir from env (set by TestIntegrationCommand)
        self::$tempDir = getenv('FENNEC_INTEGRATION_TEMP') ?: '';

        if (!self::$tempDir || !is_dir(self::$tempDir)) {
            $this->markTestSkipped('Integration tests require FENNEC_INTEGRATION_TEMP env var. Run: ./forge test:integration');
        }

        $dbFile = self::$tempDir . '/var/database.sqlite';

        if (!file_exists($dbFile)) {
            $this->markTestSkipped('Database file not found. Run: ./forge test:integration');
        }

        // Setup Env to point to temp dir
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, true);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, [
            'DB_DRIVER' => 'sqlite',
            'SQLITE_DB' => $dbFile,
            'SECRET_KEY' => base64_encode(random_bytes(32)),
            'APP_ENV' => 'dev',
        ]);

        // Reset DB manager to use new env
        if (method_exists(DB::class, 'resetManager')) {
            DB::resetManager();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);

        if (method_exists(DB::class, 'resetManager')) {
            DB::resetManager();
        }
    }

    protected function query(string $sql, array $params = []): array
    {
        $stmt = DB::raw($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $rows = $this->query($sql, $params);

        return $rows[0] ?? null;
    }

    protected function tableExists(string $table): bool
    {
        $rows = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=:name",
            ['name' => $table]
        );

        return count($rows) > 0;
    }
}
