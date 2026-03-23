<?php

namespace Fennec\Core\Migration;

use Fennec\Core\DB;

class MigrationRunner
{
    private string $connection;
    private string $migrationsPath;

    public function __construct(string $connection = 'default')
    {
        $this->connection = $connection;
        $this->migrationsPath = FENNEC_BASE_PATH . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Create the migrations table if it does not exist.
     */
    private function ensureMigrationsTable(): void
    {
        $db = DB::connection($this->connection);
        $sql = $db->getDriver()->getMigrationsTableSql();

        DB::raw($sql, [], $this->connection);
    }

    /**
     * Run all pending migrations.
     */
    public function migrate(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        $pending = array_diff($files, $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatch();
        $migrated = [];

        foreach ($pending as $file) {
            $migration = require $this->migrationsPath . '/' . $file . '.php';

            DB::transaction(function () use ($migration, $file, $batch) {
                DB::raw($migration['up'], [], $this->connection);
                DB::raw(
                    'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                    [$file, $batch],
                    $this->connection
                );
            }, $this->connection);

            $migrated[] = $file;
        }

        return $migrated;
    }

    /**
     * Rollback the last batch(es) of migrations.
     */
    public function rollback(int $steps = 1): array
    {
        $stmt = DB::raw(
            'SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ?',
            [$steps],
            $this->connection
        );
        $batches = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($batches)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($batches), '?'));
        $stmt = DB::raw(
            "SELECT migration FROM migrations WHERE batch IN ({$placeholders}) ORDER BY id DESC",
            $batches,
            $this->connection
        );
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $rolledBack = [];

        foreach ($migrations as $file) {
            $path = $this->migrationsPath . '/' . $file . '.php';

            if (!file_exists($path)) {
                continue;
            }

            $migration = require $path;

            DB::transaction(function () use ($migration, $file) {
                DB::raw($migration['down'], [], $this->connection);
                DB::raw(
                    'DELETE FROM migrations WHERE migration = ?',
                    [$file],
                    $this->connection
                );
            }, $this->connection);

            $rolledBack[] = $file;
        }

        return $rolledBack;
    }

    /**
     * Get migration status.
     */
    public function status(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();

        $status = [];

        foreach ($files as $file) {
            $status[] = [
                'migration' => $file,
                'ran' => in_array($file, $ran, true),
            ];
        }

        return $status;
    }

    /**
     * Drop all tables and re-run all migrations.
     */
    public function fresh(): array
    {
        // Rollback everything
        $stmt = DB::raw(
            'SELECT COUNT(DISTINCT batch) as total FROM migrations',
            [],
            $this->connection
        );
        $total = (int) $stmt->fetchColumn();

        if ($total > 0) {
            $this->rollback($total);
        }

        return $this->migrate();
    }

    /**
     * Get sorted migration filenames (without .php extension).
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = [];

        foreach (scandir($this->migrationsPath) as $file) {
            if (str_ends_with($file, '.php')) {
                $files[] = substr($file, 0, -4);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Get already-run migration names.
     */
    private function getRanMigrations(): array
    {
        $stmt = DB::raw(
            'SELECT migration FROM migrations ORDER BY id',
            [],
            $this->connection
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get the next batch number.
     */
    private function getNextBatch(): int
    {
        $stmt = DB::raw(
            'SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations',
            [],
            $this->connection
        );

        return (int) $stmt->fetchColumn();
    }
}
