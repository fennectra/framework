<?php

namespace Fennec\Core;

class TenantManager
{
    /** @var array<string, array{host: string, port: string, db: string, user: string, password: string}> */
    private array $tenants = [];

    private ?string $currentTenant = null;

    /**
     * Mapping domaine/port → identifiant tenant.
     * @var array<string, string>
     */
    private array $domainMap = [];

    /** @var array<string, string> */
    private array $portMap = [];

    /**
     * Charge la configuration des tenants depuis le fichier config.
     */
    public function loadConfig(string $configPath): void
    {
        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;

        // Charger le mapping domaine → tenant
        foreach ($config['domains'] ?? [] as $domain => $tenantId) {
            $this->domainMap[$domain] = $tenantId;
        }

        // Charger le mapping port → tenant
        foreach ($config['ports'] ?? [] as $port => $tenantId) {
            $this->portMap[(string) $port] = $tenantId;
        }

        // Charger les credentials DB de chaque tenant depuis les env vars
        foreach ($config['tenants'] ?? [] as $tenantId => $tenantConfig) {
            $this->tenants[$tenantId] = [
                'host'     => Env::get($tenantConfig['host'], 'localhost'),
                'port'     => Env::get($tenantConfig['port'], '5432'),
                'db'       => Env::get($tenantConfig['db'], $tenantId),
                'user'     => Env::get($tenantConfig['user'], 'postgres'),
                'password' => Env::get($tenantConfig['password'], ''),
            ];
        }
    }

    /**
     * Résout le tenant depuis le Host header et le port de la requête.
     * Priorité : domaine exact > port.
     */
    public function resolveFromRequest(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $port = (string) ($_SERVER['SERVER_PORT'] ?? '');

        // Extraire le domaine sans le port (host:port)
        $domain = strtolower(explode(':', $host)[0]);

        // 1. Match par domaine exact
        if (isset($this->domainMap[$domain])) {
            $this->currentTenant = $this->domainMap[$domain];

            return $this->currentTenant;
        }

        // 2. Match par domaine wildcard (*.example.com)
        foreach ($this->domainMap as $pattern => $tenantId) {
            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1); // .example.com
                if (str_ends_with($domain, $suffix) && $domain !== substr($suffix, 1)) {
                    $this->currentTenant = $tenantId;

                    return $this->currentTenant;
                }
            }
        }

        // 3. Match par port
        if ($port !== '' && isset($this->portMap[$port])) {
            $this->currentTenant = $this->portMap[$port];

            return $this->currentTenant;
        }

        return null;
    }

    /**
     * Définit manuellement le tenant courant (utile pour les tests/CLI).
     */
    public function setTenant(?string $tenantId): void
    {
        if ($tenantId !== null && !isset($this->tenants[$tenantId])) {
            throw new \InvalidArgumentException("Tenant inconnu : {$tenantId}");
        }

        $this->currentTenant = $tenantId;
    }

    /**
     * Retourne l'identifiant du tenant courant.
     */
    public function current(): ?string
    {
        return $this->currentTenant;
    }

    /**
     * Retourne les credentials DB du tenant courant, ou null si aucun tenant.
     *
     * @return array{host: string, port: string, db: string, user: string, password: string}|null
     */
    public function getConnectionConfig(): ?array
    {
        if ($this->currentTenant === null) {
            return null;
        }

        return $this->tenants[$this->currentTenant] ?? null;
    }

    /**
     * Retourne les credentials d'un tenant spécifique.
     *
     * @return array{host: string, port: string, db: string, user: string, password: string}|null
     */
    public function getTenantConfig(string $tenantId): ?array
    {
        return $this->tenants[$tenantId] ?? null;
    }

    /**
     * Vérifie si le multi-tenancy est configuré.
     */
    public function isEnabled(): bool
    {
        return !empty($this->tenants);
    }

    /**
     * Retourne la liste des tenants configurés.
     *
     * @return string[]
     */
    public function getTenantIds(): array
    {
        return array_keys($this->tenants);
    }

    /**
     * Reset le tenant courant (entre requêtes en mode worker).
     */
    public function reset(): void
    {
        $this->currentTenant = null;
    }
}
