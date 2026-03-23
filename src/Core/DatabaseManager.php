<?php

namespace Fennec\Core;

class DatabaseManager
{
    /** @var array<string, Database> */
    private array $connections = [];

    private ?TenantManager $tenantManager = null;

    public function setTenantManager(TenantManager $tenantManager): void
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Récupère une connexion par nom (lazy connect).
     * Si un tenant est actif et qu'on demande 'default', on utilise la connexion du tenant.
     */
    public function connection(string $name = 'default'): Database
    {
        // Résoudre la connexion tenant pour 'default'
        $resolvedName = $this->resolveTenantConnection($name);

        if (!isset($this->connections[$resolvedName])) {
            if ($resolvedName !== $name && $this->tenantManager !== null) {
                // Connexion tenant : créer avec les credentials du tenant
                $config = $this->tenantManager->getConnectionConfig();
                if ($config !== null) {
                    $this->connections[$resolvedName] = new Database($resolvedName, $config);

                    return $this->connections[$resolvedName];
                }
            }

            $this->connections[$resolvedName] = new Database($resolvedName);
        }

        return $this->connections[$resolvedName];
    }

    /**
     * Résout le nom de connexion réel en tenant compte du tenant actif.
     */
    private function resolveTenantConnection(string $name): string
    {
        if ($name !== 'default' || $this->tenantManager === null) {
            return $name;
        }

        $tenant = $this->tenantManager->current();
        if ($tenant === null) {
            return $name;
        }

        return 'tenant_' . $tenant;
    }

    /**
     * Récupère ou reconnecte automatiquement si la connexion est morte.
     */
    public function reconnectIfMissing(string $name = 'default'): Database
    {
        $db = $this->connection($name);

        if (!$db->isConnected()) {
            $db->reconnect();
        }

        return $db;
    }

    /**
     * Ferme et supprime une ou toutes les connexions.
     * Appeler en fin de requête en mode worker.
     */
    public function purge(?string $name = null): void
    {
        if ($name !== null) {
            if (isset($this->connections[$name])) {
                $this->connections[$name]->disconnect();
                unset($this->connections[$name]);
            }

            return;
        }

        // Purge toutes les connexions
        foreach ($this->connections as $db) {
            $db->disconnect();
        }
        $this->connections = [];
    }

    /**
     * Rollback les transactions orphelines sur toutes les connexions.
     * Utile en mode worker pour éviter les locks.
     */
    public function rollbackOrphanedTransactions(): void
    {
        foreach ($this->connections as $db) {
            $pdo = $db->getConnection();
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    /**
     * Nettoyage complet entre requêtes (mode worker).
     * Rollback les transactions orphelines puis purge les connexions.
     */
    public function flush(): void
    {
        $this->rollbackOrphanedTransactions();
        $this->purge();
    }

    /**
     * Retourne les noms de connexions actives.
     */
    public function getActiveConnections(): array
    {
        return array_keys($this->connections);
    }
}
