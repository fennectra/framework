<?php

namespace Fennec\Middleware;

use Fennec\Core\Env;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 0');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // CSP permissif pour docs (Scalar charge des scripts CDN)
        $docsPrefix = Env::get('DOCS_PREFIX', '/docs');
        if (str_starts_with($request->getUri(), $docsPrefix)) {
            header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:");
        } else {
            header("Content-Security-Policy: default-src 'self'; script-src 'none'; frame-ancestors 'none'");
        }

        return $next($request);
    }
}
