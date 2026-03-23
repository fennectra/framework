<?php

namespace Fennec\Middleware;

use Fennec\Core\Env;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;

class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[]|null */
    private ?array $allowedOrigins = null;

    public function handle(Request $request, callable $next): mixed
    {
        $origin = $request->getServer('HTTP_ORIGIN', '');
        $allowed = $this->resolveOrigin($origin);

        if ($allowed !== null) {
            header("Access-Control-Allow-Origin: {$allowed}");
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Preflight OPTIONS → réponse immédiate (pas d'exit en mode worker)
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);

            return null;
        }

        return $next($request);
    }

    private function resolveOrigin(string $origin): ?string
    {
        // Pas d'origin = pas de header CORS
        if ($origin === '') {
            return null;
        }

        // Mode dev : tout autoriser
        if (Env::get('APP_ENV', 'prod') === 'dev') {
            return $origin;
        }

        $allowed = $this->getAllowedOrigins();

        // Pas de config = bloquer
        if (empty($allowed)) {
            return null;
        }

        // Wildcard explicite
        if (in_array('*', $allowed, true)) {
            return $origin;
        }

        // Whitelist
        if (in_array($origin, $allowed, true)) {
            return $origin;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getAllowedOrigins(): array
    {
        if ($this->allowedOrigins !== null) {
            return $this->allowedOrigins;
        }

        $raw = Env::get('CORS_ALLOWED_ORIGINS', '');

        if ($raw === '') {
            $this->allowedOrigins = [];

            return $this->allowedOrigins;
        }

        $this->allowedOrigins = array_map('trim', explode(',', $raw));

        return $this->allowedOrigins;
    }
}
