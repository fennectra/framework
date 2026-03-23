<?php

namespace Fennec\Core;

class DB
{
    /**
     * Récupère une connexion Database via le DatabaseManager.
     */
    public static function connection(string $name = 'default'): Database
    {
        return self::manager()->reconnectIfMissing($name);
    }

    /**
     * Crée un QueryBuilder pour la table spécifiée.
     */
    public static function table(string $table, string $connection = 'default'): QueryBuilder
    {
        return new QueryBuilder(self::connection($connection), $table);
    }

    /**
     * Exécute une requête SQL brute.
     */
    public static function raw(string $sql, array $params = [], string $connection = 'default'): \PDOStatement
    {
        return self::connection($connection)->query($sql, $params);
    }

    /**
     * Exécute un callback dans une transaction.
     */
    public static function transaction(callable $callback, string $connection = 'default'): mixed
    {
        $pdo = self::connection($connection)->getConnection();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /**
     * Purge toutes les connexions (mode worker).
     */
    public static function purge(?string $name = null): void
    {
        self::manager()->purge($name);
    }

    /**
     * Nettoyage complet entre requêtes (rollback + purge).
     */
    public static function flush(): void
    {
        self::manager()->flush();
    }

    /**
     * Reset le fallback manager (pour les tests).
     */
    public static function resetManager(): void
    {
        self::$fallbackManager = null;
    }

    private static ?DatabaseManager $fallbackManager = null;

    /**
     * Accès au DatabaseManager.
     */
    private static function manager(): DatabaseManager
    {
        try {
            return Container::getInstance()->get(DatabaseManager::class);
        } catch (\Throwable) {
            // Fallback si pas de container (tests)
            self::$fallbackManager ??= new DatabaseManager();

            return self::$fallbackManager;
        }
    }
}
