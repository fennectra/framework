<?php

namespace App\Middleware;

use App\Models\User;
use Fennec\Core\Container;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;

class Auth
{
    /**
     * Handle the authentication middleware.
     *
     * Usage in routes:
     *   [Auth::class, []]                          — just check authenticated
     *   [Auth::class, ['role:admin']]               — check user has 'admin' role
     *   [Auth::class, ['permission:users.create']]  — check user has 'users.create' permission
     *
     * The permission check follows the chain:
     *   user → user_roles → roles → role_permissions → permissions
     */
    public function handle(array $params = []): void
    {
        $rawToken = $this->extractToken();

        if (!$rawToken) {
            throw new HttpException(401, 'Authentication required.');
        }

        $user = $this->validateToken($rawToken);

        if (!$user) {
            throw new HttpException(401, 'Invalid or expired token.');
        }

        if (!$user->is_active) {
            throw new HttpException(403, 'Account is not active.');
        }

        // Store full User object in request for controllers
        $_REQUEST['__auth_user'] = $user;

        // Check role/permission constraints
        foreach ($params as $constraint) {
            if (str_starts_with($constraint, 'role:')) {
                $roleName = substr($constraint, 5);
                if (!$user->hasRole($roleName)) {
                    throw new HttpException(403, "Required role: {$roleName}.");
                }
            } elseif (str_starts_with($constraint, 'permission:')) {
                $permissionName = substr($constraint, 11);
                if (!$user->hasPermission($permissionName)) {
                    throw new HttpException(403, "Required permission: {$permissionName}.");
                }
            }
        }
    }

    /**
     * Get the currently authenticated user.
     */
    public static function user(): ?User
    {
        return $_REQUEST['__auth_user'] ?? null;
    }

    /**
     * Extract Bearer token from the Authorization header.
     */
    private function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate a JWT token using JwtService and return the associated user.
     */
    private function validateToken(string $rawToken): ?User
    {
        $container = Container::getInstance();
        $jwt = $container->get(JwtService::class);

        $claims = $jwt->decode($rawToken);

        if (!$claims || !isset($claims['sub'])) {
            return null;
        }

        $email = $claims['sub'];

        // Verify the token matches what is stored in DB
        $user = User::findByEmailAndToken($email, $rawToken);

        if (!$user) {
            return null;
        }

        return $user;
    }
}