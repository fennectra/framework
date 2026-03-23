<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiWorkerController
{
    use UiHelper;

    public function stats(): void
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

    public function restart(): void
    {
        SecurityLogger::track('worker.restart', ['by' => 'admin_ui']);

        if (function_exists('frankenphp_handle_request')) {
            $GLOBALS['__fennec_should_stop'] = true;
        }

        Response::json(['success' => true, 'message' => 'Worker restart signal sent']);
    }
}
