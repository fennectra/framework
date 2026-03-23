<?php

namespace Fennec\Core\Database;

class SQLiteDriver implements DatabaseDriverInterface
{
    public function buildDsn(array $config): string
    {
        $db = $config['db'];

        if ($db === ':memory:') {
            return 'sqlite::memory:';
        }

        return "sqlite:{$db}";
    }

    public function getDefaultPort(): string
    {
        return '';
    }

    public function getDefaultHost(): string
    {
        return '';
    }

    public function getName(): string
    {
        return 'sqlite';
    }

    public function getEnvPrefix(): string
    {
        return 'SQLITE';
    }

    public function getMigrationsTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) UNIQUE NOT NULL,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )';
    }

    public function getPdoOptions(): array
    {
        return [];
    }
}
