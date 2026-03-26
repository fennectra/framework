<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Env;
use Fennec\Core\JwtService;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiController
{
    use UiHelper;

    // ─── Auth ───

    public function login(): void
    {
        $body = $this->body();
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

    public function refresh(): void
    {
        $body = $this->body();
        $refreshToken = $body['refresh_token'] ?? '';

        if (!$refreshToken) {
            Response::json(['error' => 'Refresh token is required'], 422);

            return;
        }

        $jwt = new JwtService();
        $claims = $jwt->decode($refreshToken);

        if (!$claims || ($claims['type'] ?? '') !== 'refresh') {
            Response::json(['error' => 'Invalid refresh token'], 401);

            return;
        }

        $email = $claims['sub'] ?? '';
        $adminEmail = Env::get('UI_ADMIN_EMAIL') ?: '';
        $allowedEmails = array_map('trim', explode(',', $adminEmail));

        if (!in_array($email, $allowedEmails, true)) {
            Response::json(['error' => 'Not authorized'], 403);

            return;
        }

        $accessToken = $jwt->generateAccessToken($email);
        $newRefreshToken = $jwt->generateRefreshToken($email, $accessToken['rand']);

        Response::json([
            'token' => $accessToken['token'],
            'refresh_token' => $newRefreshToken,
            'expires_at' => $accessToken['exp'],
            'email' => $email,
        ]);
    }

    public function me(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        $jwt = new JwtService();
        $claims = $jwt->decode($token);

        Response::json([
            'email' => $claims['sub'] ?? '',
            'exp' => $claims['exp'] ?? 0,
            'role' => 'super_admin',
        ]);
    }

    // ─── Dashboard ───

    public function dashboard(): void
    {
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $memLimit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '256M');
        $isWorker = isset($_SERVER['FRANKENPHP_WORKER']);

        $uptime = isset($GLOBALS['__fennec_worker_start'])
            ? time() - $GLOBALS['__fennec_worker_start']
            : time() - ($_SERVER['REQUEST_TIME'] ?? time());

        $totalRequests = $GLOBALS['__fennec_request_count'] ?? 0;

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
}
