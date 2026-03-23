<?php

namespace Fennec\Core;

use Fennec\Core\Database\DatabaseDriverInterface;
use Fennec\Core\Database\DriverFactory;

class Database
{
    private static array $instances = [];
    private ?\PDO $connection = null;
    private string $name;
    private DatabaseDriverInterface $driver;

    /** @var array{host: string, port: string, db: string, user: string, password: string}|null */
    private ?array $config;

    /**
     * @param string $name Nom de la connexion
     * @param array{host: string, port: string, db: string, user: string, password: string}|null $config
     *        Credentials explicites (multi-tenancy). Si null, lit les env vars.
     * @param DatabaseDriverInterface|null $driver Driver DB (pgsql par defaut)
     */
    public function __construct(string $name = 'default', ?array $config = null, ?DatabaseDriverInterface $driver = null)
    {
        $this->name = $name;
        $this->config = $config;
        $this->driver = $driver ?? DriverFactory::make(Env::get('DB_DRIVER', 'pgsql'));
        $this->connect();
    }

    /**
     * Établit la connexion PDO.
     */
    private function connect(): void
    {
        if ($this->config !== null) {
            // Credentials explicites (tenant)
            $host     = $this->config['host'];
            $port     = $this->config['port'];
            $dbname   = $this->config['db'];
            $user     = $this->config['user'];
            $password = $this->config['password'];
        } else {
            // Lecture depuis les variables d'environnement
            $envPrefix = $this->driver->getEnvPrefix();
            $prefix = $this->name === 'default' ? $envPrefix : $envPrefix . '_' . strtoupper($this->name);

            $host     = Env::get("{$prefix}_HOST", $this->driver->getDefaultHost());
            $port     = Env::get("{$prefix}_PORT", $this->driver->getDefaultPort());
            $dbname   = Env::get("{$prefix}_DB", '');
            $user     = Env::get("{$prefix}_USER", '');
            $password = Env::get("{$prefix}_PASSWORD", '');
        }

        $dsn = $this->driver->buildDsn([
            'host' => $host,
            'port' => $port,
            'db' => $dbname,
        ]);

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ] + $this->driver->getPdoOptions();

        $this->connection = new \PDO($dsn, $user, $password, $options);
    }

    /**
     * Backward compat — délègue au DatabaseManager si disponible.
     */
    public static function getInstance(string $name = 'default'): self
    {
        // Utiliser le DatabaseManager via Container si disponible
        try {
            $manager = Container::getInstance()->get(DatabaseManager::class);

            return $manager->connection($name);
        } catch (\Throwable) {
            // Fallback : singleton direct (tests, CLI sans container)
            if (!isset(self::$instances[$name])) {
                self::$instances[$name] = new self($name);
            }

            return self::$instances[$name];
        }
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        if (class_exists(\Fennec\Core\Profiler\Profiler::class, false)) {
            $profiler = \Fennec\Core\Profiler\Profiler::getInstance();
            $profiler?->addQuery($sql, $params, (microtime(true) - $start) * 1000);
        }

        return $stmt;
    }

    /**
     * Vérifie si la connexion PDO est encore vivante.
     */
    public function isConnected(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $this->connection->query('SELECT 1');

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Ferme la connexion.
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Recrée la connexion.
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Retourne le nom de la connexion.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retourne le driver de la connexion.
     */
    public function getDriver(): DatabaseDriverInterface
    {
        return $this->driver;
    }

    /**
     * Purge le cache singleton legacy (mode worker).
     */
    public static function clearInstances(): void
    {
        foreach (self::$instances as $db) {
            $db->disconnect();
        }
        self::$instances = [];
    }

    /**
     * Retourne le nombre de connexions en cache.
     */
    public static function instanceCount(): int
    {
        return count(self::$instances);
    }
}
