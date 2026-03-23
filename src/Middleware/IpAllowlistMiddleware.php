<?php

namespace Fennec\Middleware;

use Fennec\Core\Env;
use Fennec\Core\HttpException;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;
use Fennec\Core\Security\SecurityLogger;

/**
 * Middleware de restriction par IP (ISO 27001 A.8.5).
 *
 * Usage en route :
 *   $router->group(['middleware' => [[IpAllowlistMiddleware::class]]], ...);
 *
 * Configuration via IP_ALLOWLIST dans .env (comma-separated).
 * Supporte les CIDR : 10.0.0.0/8, 192.168.1.0/24
 */
class IpAllowlistMiddleware implements MiddlewareInterface
{
    /** @var string[]|null */
    private ?array $allowedIps = null;

    public function handle(Request $request, callable $next): mixed
    {
        $allowed = $this->getAllowedIps();

        // Si pas de config, laisser passer (opt-in)
        if (empty($allowed)) {
            return $next($request);
        }

        $clientIp = $request->getServer('REMOTE_ADDR', '');

        if (!$this->isAllowed($clientIp, $allowed)) {
            SecurityLogger::alert('access.ip_blocked', [
                'blocked_ip' => $clientIp,
            ]);

            throw new HttpException(403, 'Acces refuse depuis cette adresse IP');
        }

        return $next($request);
    }

    /**
     * @param string[] $allowed
     */
    private function isAllowed(string $ip, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if (str_contains($entry, '/')) {
                if (self::matchesCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifie si une IP est dans un range CIDR.
     */
    private static function matchesCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$subnet, $bits] = $parts;
        $bits = (int) $bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * @return string[]
     */
    private function getAllowedIps(): array
    {
        if ($this->allowedIps !== null) {
            return $this->allowedIps;
        }

        $raw = Env::get('IP_ALLOWLIST', '');

        if ($raw === '') {
            $this->allowedIps = [];

            return $this->allowedIps;
        }

        $this->allowedIps = array_map('trim', explode(',', $raw));

        return $this->allowedIps;
    }
}
