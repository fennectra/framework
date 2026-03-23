<?php

namespace Fennec\Middleware;

use App\Models\User;
use Fennec\Core\Container;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;
use Fennec\Core\Security\SecurityLogger;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ?array $roles = null,
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $rawToken = $this->extractBearerToken($request);
        if (!$rawToken) {
            SecurityLogger::alert('auth.missing_token', [
                'uri' => $request->getUri(),
            ]);

            throw new HttpException(401, 'Token invalide ou expiré');
        }

        // Validation JWT via firebase/php-jwt
        $jwt = Container::getInstance()->get(JwtService::class);
        $claims = $jwt->decode($rawToken);

        if ($claims === null) {
            SecurityLogger::alert('auth.invalid_token', [
                'uri' => $request->getUri(),
            ]);

            throw new HttpException(401, 'Token invalide ou expiré');
        }

        $email = $claims['sub'] ?? null;
        if (!$email) {
            SecurityLogger::alert('auth.invalid_claims', [
                'uri' => $request->getUri(),
            ]);

            throw new HttpException(401, 'Token invalide');
        }

        // Vérification en BDD
        $user = User::findByEmailAndToken($email, $rawToken);
        if (!$user) {
            SecurityLogger::alert('auth.revoked_token', [
                'email' => $email,
            ]);

            throw new HttpException(401, 'Token révoqué ou utilisateur inactif');
        }

        // Stocker l'utilisateur dans la requête ET dans $_REQUEST (backward compat)
        $request = $request->withAttribute('auth_user', $user);
        $_REQUEST['__auth_user'] = $user;

        // Vérification du rôle
        if ($this->roles !== null) {
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $this->roles, true)) {
                SecurityLogger::alert('auth.insufficient_role', [
                    'email' => $email,
                    'required' => $this->roles,
                    'actual' => $userRole,
                ]);

                throw new HttpException(403, 'Accès refusé : rôle insuffisant');
            }
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->getServer('HTTP_AUTHORIZATION')
            ?? $request->getServer('REDIRECT_HTTP_AUTHORIZATION')
            ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
