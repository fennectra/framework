<?php

namespace Fennec\Core\Database;

class PostgreSQLDriver implements DatabaseDriverInterface
{
    public function buildDsn(array $config): string
    {
        $host = $config['host'] ?? $this->getDefaultHost();
        $port = $config['port'] ?? $this->getDefaultPort();
        $db = $config['db'];

        return "pgsql:host={$host};port={$port};dbname={$db}";
    }

    public function getDefaultPort(): string
    {
        return '5432';
    }

    public function getDefaultHost(): string
    {
        return 'localhost';
    }

    public function getName(): string
    {
        return 'pgsql';
    }

    public function getEnvPrefix(): string
    {
        return 'POSTGRES';
    }

    public function getMigrationsTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )';
    }

    public function getPdoOptions(): array
    {
        return [];
    }
}
