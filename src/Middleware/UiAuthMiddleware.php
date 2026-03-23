<?php

namespace Fennec\Middleware;

use Fennec\Core\Env;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;

class UiAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $adminEmail = Env::get('UI_ADMIN_EMAIL', '');

        if (!$adminEmail) {
            throw new HttpException(503, 'UI_ADMIN_EMAIL not configured');
        }

        // Extraire le token Bearer
        $header = $request->getHeader('Authorization') ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            throw new HttpException(401, 'Unauthorized — Bearer token required');
        }

        $token = substr($header, 7);
        $jwt = new JwtService();
        $claims = $jwt->decode($token);

        if (!$claims) {
            throw new HttpException(401, 'Unauthorized — Invalid or expired token');
        }

        $email = $claims['sub'] ?? '';

        // Verifier que c'est un admin UI autorise
        $allowedEmails = array_map('trim', explode(',', $adminEmail));

        if (!in_array($email, $allowedEmails, true)) {
            throw new HttpException(403, 'Forbidden — Not a UI administrator');
        }

        return $next($request);
    }
}
