<?php

namespace Fennec\Middleware;

use Fennec\Core\HttpException;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\RateLimiter;
use Fennec\Core\Request;
use Fennec\Core\Security\SecurityLogger;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiter $limiter,
        private int $limit = 60,
        private int $window = 60,
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $ip = $request->getServer('REMOTE_ADDR', '0.0.0.0');
        $key = md5($ip . ':' . $request->getUri());

        $result = $this->limiter->check($key, $this->limit, $this->window);

        header("X-RateLimit-Limit: {$result['limit']}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['resetAt']}");

        if (!$result['allowed']) {
            $retryAfter = max(1, $result['resetAt'] - time());
            header("Retry-After: {$retryAfter}");

            SecurityLogger::alert('rate_limit.exceeded', [
                'key' => $key,
                'limit' => $this->limit,
            ]);

            throw new HttpException(429, 'Too many requests', ['retry_after' => $retryAfter]);
        }

        return $next($request);
    }
}
