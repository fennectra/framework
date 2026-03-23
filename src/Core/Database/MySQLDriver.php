<?php

namespace Fennec\Core\Database;

class MySQLDriver implements DatabaseDriverInterface
{
    public function buildDsn(array $config): string
    {
        $host = $config['host'] ?? $this->getDefaultHost();
        $port = $config['port'] ?? $this->getDefaultPort();
        $db = $config['db'];

        return "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    }

    public function getDefaultPort(): string
    {
        return '3306';
    }

    public function getDefaultHost(): string
    {
        return 'localhost';
    }

    public function getName(): string
    {
        return 'mysql';
    }

    public function getEnvPrefix(): string
    {
        return 'MYSQL';
    }

    public function getMigrationsTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    public function getPdoOptions(): array
    {
        return [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
        ];
    }
}
