<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\JwtService;
use Fennec\Core\Nf525\ClosingService;
use Fennec\Core\Nf525\FecExporter;
use Fennec\Core\Nf525\HashChainVerifier;
use Fennec\Core\Response;
use Fennec\Core\Security\AccountLockout;
use Fennec\Core\Security\SecurityLogger;
use Fennec\Core\Webhook;

class UiController
{
    // ─── Auth ───

    public function login(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        $adminEmail = Env::get('UI_ADMIN_EMAIL') ?: '';
        $adminPassword = Env::get('UI_ADMIN_PASSWORD') ?: '';

        if (!$adminEmail || !$adminPassword) {
            Response::json(['error' => 'UI admin credentials not configured'], 503);

            return;
        }

        $allowedEmails = array_map('trim', explode(',', $adminEmail));

        if (!in_array($email, $allowedEmails, true) || !hash_equals($adminPassword, $password)) {
            SecurityLogger::alert('ui.login.failed', ['email' => $email]);
            Response::json(['error' => 'Invalid credentials'], 401);

            return;
        }

        $jwt = new JwtService();
        $accessToken = $jwt->generateAccessToken($email);
        $refreshToken = $jwt->generateRefreshToken($email, $accessToken['rand']);

        SecurityLogger::track('ui.login.success', ['email' => $email]);

        Response::json([
            'token' => $accessToken['token'],
            'refresh_token' => $refreshToken,
            'expires_at' => $accessToken['exp'],
            'email' => $email,
        ]);
    }

    // ─── Dashboard ───

    public function dashboard(): void
    {
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $memLimit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '256M');
        $isWorker = isset($_SERVER['FRANKENPHP_WORKER']);

        // Uptime : worker global ou fallback sur le start du process PHP
        $uptime = isset($GLOBALS['__fennec_worker_start'])
            ? time() - $GLOBALS['__fennec_worker_start']
            : time() - ($_SERVER['REQUEST_TIME'] ?? time());

        $totalRequests = $GLOBALS['__fennec_request_count'] ?? 0;

        // Evenements recents depuis les logs securite
        $recentEvents = $this->readRecentSecurityEvents(10);

        Response::json([
            'uptime' => $uptime,
            'totalRequests' => $totalRequests,
            'requestsPerSecond' => $uptime > 0 ? round($totalRequests / max($uptime, 1), 1) : 0,
            'avgLatency' => $GLOBALS['__fennec_avg_latency'] ?? 0,
            'errorRate' => $GLOBALS['__fennec_error_rate'] ?? 0,
            'memoryUsage' => $memUsage,
            'memoryPeak' => $memPeak,
            'memoryLimit' => $memLimit,
            'phpVersion' => PHP_VERSION,
            'frankenphp' => $isWorker,
            'chart' => [],
            'recentEvents' => $recentEvents,
        ]);
    }

    private function readRecentSecurityEvents(int $limit): array
    {
        $logDir = FENNEC_BASE_PATH . '/var/logs';
        $logFiles = glob($logDir . '/security-*.log');
        if ($logFiles === false) {
            $logFiles = [];
        }
        rsort($logFiles);
        $logFiles = array_slice($logFiles, 0, 3);

        $events = [];
        foreach ($logFiles as $logFile) {
            $lines = array_filter(file($logFile, FILE_IGNORE_NEW_LINES));
            foreach (array_reverse($lines) as $line) {
                $parsed = $this->parseLogLine($line);
                if ($parsed) {
                    $events[] = [
                        'type' => $parsed['level'] === 'CRITICAL' ? 'security' : ($parsed['level'] === 'WARNING' ? 'warning' : 'info'),
                        'message' => $parsed['event'],
                        'time' => $parsed['timestamp'],
                    ];
                }
                if (count($events) >= $limit) {
                    break 2;
                }
            }
        }

        return $events;
    }

    // ─── NF525 ───

    public function nf525Invoices(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        try {
            $total = DB::raw('SELECT COUNT(*) as cnt FROM invoices')->fetchAll()[0]['cnt'] ?? 0;
            $rows = DB::raw(
                "SELECT * FROM invoices ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
            )->fetchAll();

            Response::json(['data' => $rows, 'total' => (int) $total]);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0]);
        }
    }

    public function nf525Closings(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM nf525_closings ORDER BY id DESC')->fetchAll();
            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function nf525Verify(): void
    {
        try {
            $result = HashChainVerifier::verify('invoices');
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json([
                'valid' => false,
                'total' => 0,
                'errors' => [['id' => 0, 'error' => $e->getMessage()]],
            ]);
        }
    }

    public function nf525Close(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $body['type'] ?? 'daily';
        $period = $body['period'] ?? '';

        if (!$period) {
            Response::json(['error' => 'Period is required'], 422);

            return;
        }

        try {
            $service = new ClosingService();
            $result = match ($type) {
                'daily' => $service->closeDaily($period),
                'monthly' => $service->closeMonthly($period),
                'annual' => $service->closeAnnual($period),
                default => throw new \InvalidArgumentException('Invalid closing type'),
            };

            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function nf525Fec(): void
    {
        $year = $_GET['year'] ?? date('Y');

        try {
            $exporter = new FecExporter();
            $path = $exporter->exportToFile($year);

            Response::json(['url' => '/storage/' . basename($path), 'path' => $path]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Audit ───

    public function auditLogs(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where = [];
        $action = $_GET['action'] ?? '';
        $table = $_GET['table'] ?? '';

        if ($action) {
            $where[] = "action = '" . addslashes($action) . "'";
        }
        if ($table) {
            $where[] = "auditable_type LIKE '%" . addslashes($table) . "%'";
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $total = DB::raw(
                "SELECT COUNT(*) as cnt FROM audit_logs {$whereClause}"
            )->fetchAll()[0]['cnt'] ?? 0;
            $rows = DB::raw(
                "SELECT * FROM audit_logs {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
            )->fetchAll();

            foreach ($rows as &$row) {
                $row['old_values'] = json_decode($row['old_values'] ?? '{}', true) ?? [];
                $row['new_values'] = json_decode($row['new_values'] ?? '{}', true) ?? [];
            }

            Response::json(['data' => $rows, 'total' => (int) $total]);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0]);
        }
    }

    public function auditStats(): void
    {
        try {
            $total = DB::raw('SELECT COUNT(*) as cnt FROM audit_logs')->fetchAll()[0]['cnt'] ?? 0;

            Response::json([
                'totalEntries' => (int) $total,
                'retentionDays' => (int) (Env::get('AUDIT_RETENTION_DAYS') ?: 365),
                'lastPurge' => null,
            ]);
        } catch (\Throwable) {
            Response::json([
                'totalEntries' => 0,
                'retentionDays' => 365,
                'lastPurge' => null,
            ]);
        }
    }

    public function auditPurge(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $days = (int) ($body['days'] ?? 365);
        $dryRun = (bool) ($body['dryRun'] ?? true);

        if ($days < 30) {
            Response::json(['error' => 'Minimum retention is 30 days'], 422);

            return;
        }

        try {
            $driver = Env::get('DB_DRIVER') ?: 'pgsql';
            $dateExpr = match ($driver) {
                'mysql' => "DATE_SUB(NOW(), INTERVAL {$days} DAY)",
                'sqlite' => "datetime('now', '-{$days} days')",
                default => "NOW() - INTERVAL '{$days} days'",
            };

            $count = DB::raw(
                "SELECT COUNT(*) as cnt FROM audit_logs WHERE created_at < {$dateExpr}"
            )->fetchAll()[0]['cnt'] ?? 0;

            if (!$dryRun) {
                DB::raw("DELETE FROM audit_logs WHERE created_at < {$dateExpr}");
                SecurityLogger::track('audit.purged', ['days' => $days, 'deleted' => $count]);
            }

            Response::json(['deleted' => (int) $count, 'dryRun' => $dryRun]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Security ───

    public function securityEvents(): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $logDir = FENNEC_BASE_PATH . '/var/logs';

        $logFiles = glob($logDir . '/security-*.log');
        if ($logFiles === false) {
            $logFiles = [];
        }
        rsort($logFiles);
        $logFiles = array_slice($logFiles, 0, 7);

        $events = [];
        $eventCounts = []; // compteur par type d'event pour limiter les doublons
        $maxPerType = 5;   // max 5 events du meme type

        foreach ($logFiles as $logFile) {
            $lines = array_filter(file($logFile, FILE_IGNORE_NEW_LINES));
            foreach (array_reverse($lines) as $line) {
                $parsed = $this->parseLogLine($line);
                if (!$parsed) {
                    continue;
                }

                $eventType = $parsed['event'];
                $eventCounts[$eventType] = ($eventCounts[$eventType] ?? 0) + 1;

                // Limiter les doublons : max N par type d'event
                if ($eventCounts[$eventType] <= $maxPerType) {
                    $events[] = $parsed;
                }

                if (count($events) >= $limit) {
                    break 2;
                }
            }
        }

        // Ajouter les totaux par type en metadata
        $summary = [];
        foreach ($eventCounts as $type => $count) {
            $summary[] = ['event' => $type, 'count' => $count];
        }
        usort($summary, fn ($a, $b) => $b['count'] - $a['count']);

        Response::json([
            'events' => $events,
            'summary' => $summary,
            'total' => array_sum($eventCounts),
        ]);
    }

    public function securityLockouts(): void
    {
        $locked = AccountLockout::locked();
        $result = [];

        foreach ($locked as $identifier => $data) {
            $result[] = [
                'email' => $identifier,
                'attempts' => $data['attempts'],
                'locked_until' => date('c', $data['locked_until']),
                'remaining' => $data['remaining'],
            ];
        }

        Response::json($result);
    }

    public function securityUnlock(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $body['email'] ?? '';

        if (!$email) {
            Response::json(['error' => 'Email is required'], 422);

            return;
        }

        AccountLockout::reset($email);
        SecurityLogger::track('account.unlocked', ['email' => $email, 'by' => 'admin_ui']);

        Response::json(['success' => true]);
    }

    // ─── Worker ───

    public function workerStats(): void
    {
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $memLimit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '256M');

        Response::json([
            'running' => isset($_SERVER['FRANKENPHP_WORKER']),
            'pid' => getmypid() ?: null,
            'uptime' => isset($GLOBALS['__fennec_worker_start'])
                ? time() - $GLOBALS['__fennec_worker_start']
                : 0,
            'memoryUsage' => $memUsage,
            'memoryPeak' => $memPeak,
            'memoryLimit' => $memLimit,
            'totalRequests' => $GLOBALS['__fennec_request_count'] ?? 0,
            'activeConnections' => $GLOBALS['__fennec_active_connections'] ?? 0,
            'avgResponseTime' => $GLOBALS['__fennec_avg_latency'] ?? 0,
            'threads' => (int) (ini_get('frankenphp.num_threads') ?: 4),
            'startedAt' => isset($GLOBALS['__fennec_worker_start'])
                ? date('c', $GLOBALS['__fennec_worker_start'])
                : null,
        ]);
    }

    public function workerRestart(): void
    {
        SecurityLogger::track('worker.restart', ['by' => 'admin_ui']);

        if (function_exists('frankenphp_handle_request')) {
            $GLOBALS['__fennec_should_stop'] = true;
        }

        Response::json(['success' => true, 'message' => 'Worker restart signal sent']);
    }

    // ─── Webhooks ───

    public function webhookList(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM webhooks ORDER BY id DESC')->fetchAll();

            foreach ($rows as &$row) {
                $row['events'] = json_decode($row['events'] ?? '[]', true) ?? [];
            }

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function webhookCreate(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = $body['name'] ?? '';
        $url = $body['url'] ?? '';
        $secret = $body['secret'] ?? bin2hex(random_bytes(16));
        $events = $body['events'] ?? [];
        $description = $body['description'] ?? '';

        if (!$name || !$url) {
            Response::json(['error' => 'Name and URL are required'], 422);

            return;
        }

        try {
            $eventsJson = json_encode($events);
            DB::raw(
                'INSERT INTO webhooks (name, url, secret, events, is_active, description, created_at, updated_at) VALUES (?, ?, ?, ?, true, ?, NOW(), NOW())',
                [$name, $url, $secret, $eventsJson, $description]
            );

            SecurityLogger::track('webhook.created', ['name' => $name, 'url' => $url]);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true, 'secret' => $secret]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhookUpdate(int $id): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $params = [];

        if (isset($body['name'])) {
            $fields[] = 'name = ?';
            $params[] = $body['name'];
        }
        if (isset($body['url'])) {
            $fields[] = 'url = ?';
            $params[] = $body['url'];
        }
        if (isset($body['events'])) {
            $fields[] = 'events = ?';
            $params[] = json_encode($body['events']);
        }
        if (isset($body['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $body['is_active'] ? 'true' : 'false';
        }
        if (isset($body['description'])) {
            $fields[] = 'description = ?';
            $params[] = $body['description'];
        }

        if (!$fields) {
            Response::json(['error' => 'No fields to update'], 422);

            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        try {
            DB::raw('UPDATE webhooks SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhookDelete(int $id): void
    {
        try {
            DB::raw('DELETE FROM webhooks WHERE id = ?', [$id]);
            SecurityLogger::track('webhook.deleted', ['id' => $id]);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhookDeliveries(int $id): void
    {
        try {
            $rows = DB::raw(
                'SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT 50',
                [$id]
            )->fetchAll();

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    // ─── Helpers ───

    private function parseMemoryLimit(string $limit): int
    {
        $value = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function parseLogLine(string $line): ?array
    {
        if (!preg_match('/^\[(.+?)\] \w+\.(\w+): (.+?) (\{.+\})/', $line, $m)) {
            return null;
        }

        $context = json_decode($m[4], true) ?? [];

        return [
            'timestamp' => $m[1],
            'level' => $m[2],
            'event' => $m[3],
            'context' => $context,
        ];
    }
}
