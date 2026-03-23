<?php

namespace Fennec\Core;

/**
 * Metriques worker — persiste entre les requetes en mode FrankenPHP.
 *
 * Instancie UNE SEULE FOIS avant la boucle worker, l'objet vit
 * dans le scope du worker loop et survit aux frankenphp_handle_request().
 *
 * En mode classique (PHP-FPM / built-in), les valeurs sont reinit a chaque requete.
 */
class WorkerStats
{
    private int $requestCount = 0;
    private float $bootedAt;
    private float $peakMemory = 0;

    /** Memoire avant la derniere requete (pour calculer le delta) */
    private int $memoryBeforeRequest = 0;

    /** Delta memoire de la derniere requete */
    private int $lastRequestDelta = 0;

    /** Nombre de requetes en erreur */
    private int $errorCount = 0;

    /** Derniere erreur capturee */
    private ?string $lastError = null;

    /** Historique des 20 derniers deltas memoire (pour trend) */
    private array $memoryDeltas = [];

    /** Seuil memoire en pourcentage pour alerte (0-100) */
    private int $memoryWarningPercent = 80;

    public function __construct()
    {
        $this->bootedAt = microtime(true);
        $this->peakMemory = memory_get_peak_usage(true);
        $this->memoryBeforeRequest = memory_get_usage(true);
    }

    /**
     * A appeler AVANT le handler (debut de requete).
     */
    public function beforeRequest(): void
    {
        $this->memoryBeforeRequest = memory_get_usage(true);
    }

    /**
     * A appeler APRES le handler (fin de requete).
     * Incremente le compteur, track la memoire, detecte les fuites.
     */
    public function afterRequest(): void
    {
        ++$this->requestCount;

        // Peak memoire (lifetime du worker)
        $peak = memory_get_peak_usage(true);
        if ($peak > $this->peakMemory) {
            $this->peakMemory = $peak;
        }

        // Delta memoire de cette requete
        $currentMemory = memory_get_usage(true);
        $this->lastRequestDelta = $currentMemory - $this->memoryBeforeRequest;

        // Garder les 20 derniers deltas pour analyse de tendance
        $this->memoryDeltas[] = $this->lastRequestDelta;
        if (count($this->memoryDeltas) > 20) {
            array_shift($this->memoryDeltas);
        }

        // Log si memoire haute
        $this->checkMemoryThreshold($currentMemory);
    }

    /**
     * Enregistre une erreur survenue pendant le handler.
     */
    public function recordError(\Throwable $e): void
    {
        ++$this->errorCount;
        $this->lastError = get_class($e) . ': ' . $e->getMessage();
    }

    public function getSnapshot(): array
    {
        $now = microtime(true);
        $uptimeSeconds = $now - $this->bootedAt;
        $currentMemory = memory_get_usage(true);
        $limitBytes = $this->parseLimitToBytes(ini_get('memory_limit') ?: '128M');
        $usagePercent = $limitBytes > 0 ? round(($currentMemory / $limitBytes) * 100, 1) : 0;

        return [
            'worker' => [
                'mode' => $this->detectMode(),
                'pid' => getmypid(),
                'uptime_seconds' => round($uptimeSeconds, 1),
                'uptime_human' => self::humanDuration($uptimeSeconds),
                'requests_handled' => $this->requestCount,
                'requests_per_second' => $uptimeSeconds > 0
                    ? round($this->requestCount / $uptimeSeconds, 2)
                    : 0,
                'max_requests' => (int) ($_SERVER['MAX_REQUESTS'] ?? 0),
                'errors' => $this->errorCount,
                'last_error' => $this->lastError,
            ],
            'memory' => [
                'current_mb' => round($currentMemory / 1048576, 2),
                'peak_mb' => round($this->peakMemory / 1048576, 2),
                'limit' => ini_get('memory_limit') ?: '128M',
                'usage_percent' => $usagePercent,
                'last_request_delta_kb' => round($this->lastRequestDelta / 1024, 1),
                'trend' => $this->analyzeTrend(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'extensions' => self::keyExtensions(),
                'gc_enabled' => gc_enabled(),
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Detecte le mode d'execution.
     * $_SERVER['FRANKENPHP_WORKER'] est plus precis que function_exists().
     */
    private function detectMode(): string
    {
        if (!empty($_SERVER['FRANKENPHP_WORKER'])) {
            return 'frankenphp-worker';
        }
        if (function_exists('frankenphp_handle_request')) {
            return 'frankenphp';
        }

        return PHP_SAPI; // cli, fpm-fcgi, etc.
    }

    /**
     * Analyse la tendance memoire sur les derniers deltas.
     * - "stable"  : pas de croissance significative
     * - "growing" : memoire augmente regulierement (fuite probable)
     * - "spiky"   : pics ponctuels mais retour a la normale
     */
    private function analyzeTrend(): string
    {
        if (count($this->memoryDeltas) < 5) {
            return 'insufficient_data';
        }

        $positiveCount = 0;
        $totalDelta = 0;
        foreach ($this->memoryDeltas as $delta) {
            if ($delta > 0) {
                ++$positiveCount;
            }
            $totalDelta += $delta;
        }

        $ratio = $positiveCount / count($this->memoryDeltas);

        // Plus de 70% des requetes augmentent la memoire
        if ($ratio > 0.7 && $totalDelta > 0) {
            return 'growing';
        }

        // Quelques pics mais moyenne stable
        if ($ratio > 0.3 && $totalDelta <= 0) {
            return 'spiky';
        }

        return 'stable';
    }

    /**
     * Log un warning si la memoire depasse le seuil.
     */
    private function checkMemoryThreshold(int $currentBytes): void
    {
        $limitBytes = $this->parseLimitToBytes(ini_get('memory_limit') ?: '128M');
        if ($limitBytes <= 0) {
            return;
        }

        $percent = ($currentBytes / $limitBytes) * 100;

        if ($percent >= $this->memoryWarningPercent) {
            Logger::warning('Worker memory high', [
                'current_mb' => round($currentBytes / 1048576, 2),
                'limit' => ini_get('memory_limit'),
                'percent' => round($percent, 1),
                'requests' => $this->requestCount,
                'pid' => getmypid(),
            ]);
        }

        // Alerte tendance fuite
        if ($this->analyzeTrend() === 'growing') {
            Logger::warning('Possible memory leak detected', [
                'deltas_kb' => array_map(fn ($d) => round($d / 1024, 1), $this->memoryDeltas),
                'requests' => $this->requestCount,
                'pid' => getmypid(),
            ]);
        }
    }

    /**
     * Convertit une limite PHP (128M, 1G, etc.) en bytes.
     */
    private function parseLimitToBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return 0;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private static function humanDuration(float $seconds): string
    {
        $total = (int) $seconds;
        $d = intdiv($total, 86400);
        $h = intdiv($total % 86400, 3600);
        $m = intdiv($total % 3600, 60);
        $s = $total % 60;

        $parts = [];
        if ($d > 0) {
            $parts[] = "{$d}d";
        }
        if ($h > 0) {
            $parts[] = "{$h}h";
        }
        if ($m > 0) {
            $parts[] = "{$m}m";
        }
        $parts[] = "{$s}s";

        return implode(' ', $parts);
    }

    private static function keyExtensions(): array
    {
        $check = ['pdo_pgsql', 'pdo_mysql', 'redis', 'apcu', 'opcache', 'mbstring', 'openssl', 'curl', 'gd'];
        $loaded = [];
        foreach ($check as $ext) {
            if (extension_loaded($ext)) {
                $loaded[] = $ext;
            }
        }

        return $loaded;
    }
}
