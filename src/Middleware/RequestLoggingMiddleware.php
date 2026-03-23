<?php

namespace Fennec\Middleware;

use Fennec\Core\Logger;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;

class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $requestId = bin2hex(random_bytes(8));
        $_SERVER['X_REQUEST_ID'] = $requestId;
        header("X-Request-Id: {$requestId}");

        $start = microtime(true);
        $memBefore = memory_get_usage(true);

        $result = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $memDeltaKb = round((memory_get_usage(true) - $memBefore) / 1024, 1);
        $status = http_response_code() ?: 200;
        $user = $_REQUEST['__auth_user']['email'] ?? '-';

        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');

        Logger::$level("{$request->getMethod()} {$request->getUri()} {$status} {$durationMs}ms", [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'status' => $status,
            'duration_ms' => $durationMs,
            'memory_delta_kb' => $memDeltaKb,
            'user' => $user,
            'ip' => $request->getServer('REMOTE_ADDR', '-'),
        ]);

        return $result;
    }
}
