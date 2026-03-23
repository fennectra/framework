<?php

namespace Fennec\Middleware;

use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Profiler\Profiler;
use Fennec\Core\Request;

class ProfilerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $profiler = Profiler::getInstance();

        if ($profiler === null || !$profiler->isEnabled()) {
            return $next($request);
        }

        $profiler->start($request->getMethod(), $request->getUri());

        $result = $next($request);

        $profiler->stop();

        return $result;
    }
}
