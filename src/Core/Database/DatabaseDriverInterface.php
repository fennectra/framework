<?php

namespace Fennec\Core\Database;

interface DatabaseDriverInterface
{
    /**
     * Build the PDO DSN string from the given config.
     *
     * @param array{host?: string, port?: string, db: string, user?: string, password?: string} $config
     */
    public function buildDsn(array $config): string;

    /**
     * Return the default port for this driver.
     */
    public function getDefaultPort(): string;

    /**
     * Return the default host for this driver.
     */
    public function getDefaultHost(): string;

    /**
     * Return the driver name (pgsql, mysql, sqlite).
     */
    public function getName(): string;

    /**
     * Return the env prefix for this driver (POSTGRES, MYSQL, SQLITE).
     */
    public function getEnvPrefix(): string;

    /**
     * Return SQL to create the migrations table.
     */
    public function getMigrationsTableSql(): string;

    /**
     * Return PDO options specific to this driver.
     *
     * @return array<int, mixed>
     */
    public function getPdoOptions(): array;
}
