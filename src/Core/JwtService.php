<?php

namespace Fennec\Core;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(?string $secret = null, string $algorithm = 'HS256')
    {
        $this->secret = $secret ?? Env::get('SECRET_KEY');
        $this->algorithm = $algorithm;
        $this->accessTtl = (int) Env::get('JWT_ACCESS_TTL', (string) (15 * 60));
        $this->refreshTtl = (int) Env::get('JWT_REFRESH_TTL', (string) (24 * 3600));

        if (empty($this->secret)) {
            throw new \RuntimeException('SECRET_KEY non définie. Configurez-la dans .env');
        }
    }

    /**
     * Encode un payload en token JWT.
     */
    public function encode(array $claims): string
    {
        return JWT::encode($claims, $this->secret, $this->algorithm);
    }

    /**
     * Décode et valide un token JWT. Retourne les claims ou null si invalide.
     */
    public function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return (array) $decoded;
        } catch (ExpiredException | SignatureInvalidException | \Exception) {
            return null;
        }
    }

    /**
     * Génère un access token (30 jours par défaut).
     */
    public function generateAccessToken(string $email, ?int $ttl = null): array
    {
        $ttl ??= $this->accessTtl;
        $exp = time() + $ttl;
        $rand = bin2hex(random_bytes(8));

        $token = $this->encode([
            'sub' => $email,
            'exp' => $exp,
            'rand' => $rand,
        ]);

        return ['token' => $token, 'exp' => $exp, 'rand' => $rand];
    }

    /**
     * Génère un refresh token (configurable via JWT_REFRESH_TTL).
     */
    public function generateRefreshToken(string $email, string $rand, ?int $ttl = null): string
    {
        $ttl ??= $this->refreshTtl;

        return $this->encode([
            'sub' => $email,
            'exp' => time() + $ttl,
            'rand' => $rand . 'r',
        ]);
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}
