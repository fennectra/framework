<?php

namespace Fennec\Core\OAuth;

/**
 * Abstract OpenID Connect provider.
 *
 * Extends OAuthProvider with OIDC-specific features:
 * - Auto-discovery via .well-known/openid-configuration
 * - ID token validation (JWT signed by IdP via JWKS)
 * - PKCE support (code_verifier / code_challenge)
 * - Nonce validation for replay protection
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html
 */
abstract class OidcProvider extends OAuthProvider
{
    /** @var array<string, mixed>|null Cached discovery document */
    private ?array $discoveryCache = null;

    /** @var array<string, mixed>|null Cached JWKS keys */
    private ?array $jwksCache = null;

    /**
     * The OpenID Connect issuer URL (e.g. https://accounts.google.com).
     */
    abstract protected function getIssuer(): string;

    /**
     * The OAuth2 client ID.
     */
    abstract protected function getClientId(): string;

    /**
     * The OAuth2 client secret.
     */
    abstract protected function getClientSecret(): string;

    /**
     * The redirect URI for the callback.
     */
    abstract protected function getRedirectUri(): string;

    /**
     * Additional scopes beyond 'openid'.
     *
     * @return string[]
     */
    protected function getScopes(): array
    {
        return ['email', 'profile'];
    }

    /**
     * Whether to use PKCE (Proof Key for Code Exchange).
     * Override to return true for providers that require it.
     */
    protected function usePkce(): bool
    {
        return false;
    }

    /**
     * Fetch the OpenID Connect discovery document.
     *
     * @return array<string, mixed>
     */
    public function discover(): array
    {
        if ($this->discoveryCache !== null) {
            return $this->discoveryCache;
        }

        $issuer = rtrim($this->getIssuer(), '/');
        $url = $issuer . '/.well-known/openid-configuration';
        $this->discoveryCache = $this->httpGet($url);

        return $this->discoveryCache;
    }

    /**
     * Build authorization URL with OIDC parameters.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $discovery = $this->discover();
        $authEndpoint = $discovery['authorization_endpoint']
            ?? throw new \RuntimeException('Missing authorization_endpoint in OIDC discovery');

        $scopes = array_unique(array_merge(['openid'], $this->getScopes()));
        $nonce = bin2hex(random_bytes(16));

        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($this->usePkce()) {
            $verifier = $this->generateCodeVerifier();
            $params['code_challenge'] = $this->generateCodeChallenge($verifier);
            $params['code_challenge_method'] = 'S256';
            // Store verifier — caller must persist it (e.g. in session)
            $params['_code_verifier'] = $verifier;
        }

        // Store nonce — caller must persist it for validation
        $params['_nonce'] = $nonce;

        return $authEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     * Pass the code_verifier if PKCE was used.
     */
    public function getAccessToken(string $code, ?string $codeVerifier = null): OAuthToken
    {
        $discovery = $this->discover();
        $tokenEndpoint = $discovery['token_endpoint']
            ?? throw new \RuntimeException('Missing token_endpoint in OIDC discovery');

        $data = [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
            'grant_type' => 'authorization_code',
        ];

        if ($codeVerifier !== null) {
            $data['code_verifier'] = $codeVerifier;
        }

        $response = $this->httpPost($tokenEndpoint, $data);

        return new OAuthToken(
            accessToken: $response['access_token'],
            refreshToken: $response['refresh_token'] ?? null,
            expiresIn: isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            tokenType: $response['token_type'] ?? 'Bearer',
            idToken: $response['id_token'] ?? null,
        );
    }

    /**
     * Get user info from the userinfo endpoint.
     */
    public function getUserInfo(string $accessToken): OAuthUser
    {
        $discovery = $this->discover();
        $userinfoEndpoint = $discovery['userinfo_endpoint'] ?? null;

        if ($userinfoEndpoint === null) {
            throw new \RuntimeException('Missing userinfo_endpoint in OIDC discovery');
        }

        $data = $this->httpGet($userinfoEndpoint, [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        return new OAuthUser(
            id: (string) ($data['sub'] ?? $data['id'] ?? ''),
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            avatar: $data['picture'] ?? null,
            provider: $this->getProviderName(),
            raw: $data,
        );
    }

    /**
     * Validate and decode the id_token from the token response.
     * Returns OidcClaims with all standard OIDC claims.
     *
     * @param string      $idToken  The raw id_token JWT
     * @param string|null $nonce    Expected nonce (from authorization request)
     */
    public function validateIdToken(string $idToken, ?string $nonce = null): OidcClaims
    {
        // Split JWT parts
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid id_token format');
        }

        // Decode header to get kid and alg
        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!$header || !isset($header['alg'])) {
            throw new \RuntimeException('Invalid id_token header');
        }

        $kid = $header['kid'] ?? null;
        $alg = $header['alg'];

        // Get the signing key from JWKS
        $publicKey = $this->getSigningKey($kid, $alg);

        // Verify signature
        $this->verifyJwtSignature($parts, $publicKey, $alg);

        // Decode payload
        $claims = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($claims)) {
            throw new \RuntimeException('Invalid id_token payload');
        }

        // Validate standard claims
        $this->validateClaims($claims, $nonce);

        return OidcClaims::fromArray($claims);
    }

    /**
     * Get the provider identifier name.
     */
    protected function getProviderName(): string
    {
        return 'oidc';
    }

    // ─── PKCE helpers ────────────────────────────────────────────

    /**
     * Generate a cryptographic code verifier for PKCE.
     */
    public function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate S256 code challenge from verifier.
     */
    public function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    // ─── JWKS and signature verification ─────────────────────────

    /**
     * Fetch JWKS (JSON Web Key Set) from the IdP.
     *
     * @return array<string, mixed>
     */
    protected function fetchJwks(): array
    {
        if ($this->jwksCache !== null) {
            return $this->jwksCache;
        }

        $discovery = $this->discover();
        $jwksUri = $discovery['jwks_uri']
            ?? throw new \RuntimeException('Missing jwks_uri in OIDC discovery');

        $this->jwksCache = $this->httpGet($jwksUri);

        return $this->jwksCache;
    }

    /**
     * Get the public key for signature verification.
     */
    protected function getSigningKey(?string $kid, string $alg): \OpenSSLAsymmetricKey
    {
        $jwks = $this->fetchJwks();
        $keys = $jwks['keys'] ?? [];

        $key = null;
        foreach ($keys as $jwk) {
            if ($kid !== null && ($jwk['kid'] ?? null) === $kid) {
                $key = $jwk;
                break;
            }
            // If no kid, try to match by alg and use
            if ($kid === null && ($jwk['alg'] ?? null) === $alg && ($jwk['use'] ?? 'sig') === 'sig') {
                $key = $jwk;
                break;
            }
        }

        // Fallback: use first key if only one
        if ($key === null && count($keys) === 1) {
            $key = $keys[0];
        }

        if ($key === null) {
            throw new \RuntimeException('No matching key found in JWKS for kid=' . ($kid ?? 'null'));
        }

        return $this->jwkToPublicKey($key);
    }

    /**
     * Convert a JWK (RSA) to an OpenSSL public key.
     *
     * @param array<string, string> $jwk
     */
    protected function jwkToPublicKey(array $jwk): \OpenSSLAsymmetricKey
    {
        if (($jwk['kty'] ?? '') !== 'RSA') {
            throw new \RuntimeException('Only RSA keys are supported, got: ' . ($jwk['kty'] ?? 'unknown'));
        }

        $n = $this->base64UrlDecode($jwk['n'] ?? '');
        $e = $this->base64UrlDecode($jwk['e'] ?? '');

        // Build DER-encoded RSA public key
        $modulus = $this->encodeAsn1Integer($n);
        $exponent = $this->encodeAsn1Integer($e);

        // RSAPublicKey SEQUENCE
        $rsaPublicKey = $this->encodeAsn1Sequence($modulus . $exponent);

        // BitString wrapping
        $bitString = chr(0x03) . $this->encodeAsn1Length(strlen($rsaPublicKey) + 1) . chr(0x00) . $rsaPublicKey;

        // Algorithm identifier: rsaEncryption OID
        $algorithmOid = pack('H*', '06092a864886f70d010101'); // 1.2.840.113549.1.1.1
        $algorithmNull = pack('H*', '0500');
        $algorithmIdentifier = $this->encodeAsn1Sequence($algorithmOid . $algorithmNull);

        // SubjectPublicKeyInfo SEQUENCE
        $der = $this->encodeAsn1Sequence($algorithmIdentifier . $bitString);

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . '-----END PUBLIC KEY-----';

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException('Failed to parse RSA public key from JWK');
        }

        return $key;
    }

    /**
     * Verify JWT signature using RSA.
     *
     * @param string[] $parts JWT parts [header, payload, signature]
     */
    protected function verifyJwtSignature(array $parts, \OpenSSLAsymmetricKey $publicKey, string $alg): void
    {
        $data = $parts[0] . '.' . $parts[1];
        $signature = $this->base64UrlDecode($parts[2]);

        $algorithm = match ($alg) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => throw new \RuntimeException('Unsupported id_token algorithm: ' . $alg),
        };

        $result = openssl_verify($data, $signature, $publicKey, $algorithm);
        if ($result !== 1) {
            throw new \RuntimeException('id_token signature verification failed');
        }
    }

    /**
     * Validate standard OIDC claims.
     *
     * @param array<string, mixed> $claims
     */
    protected function validateClaims(array $claims, ?string $expectedNonce = null): void
    {
        // Issuer must match
        $issuer = rtrim($this->getIssuer(), '/');
        $claimIssuer = rtrim($claims['iss'] ?? '', '/');
        if ($claimIssuer !== $issuer) {
            throw new \RuntimeException(
                'id_token issuer mismatch: expected ' . $issuer . ', got ' . $claimIssuer
            );
        }

        // Audience must contain our client_id
        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (!in_array($this->getClientId(), $audiences, true)) {
            throw new \RuntimeException('id_token audience does not contain client_id');
        }

        // Token must not be expired (5 second leeway)
        $exp = $claims['exp'] ?? 0;
        if ($exp < time() - 5) {
            throw new \RuntimeException('id_token has expired');
        }

        // Nonce validation if provided
        if ($expectedNonce !== null) {
            $tokenNonce = $claims['nonce'] ?? null;
            if ($tokenNonce !== $expectedNonce) {
                throw new \RuntimeException('id_token nonce mismatch');
            }
        }
    }

    // ─── ASN.1 / DER encoding helpers ────────────────────────────

    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    private function encodeAsn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), chr(0));

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function encodeAsn1Integer(string $data): string
    {
        // Prepend 0x00 if high bit is set (unsigned integer)
        if (ord($data[0]) & 0x80) {
            $data = chr(0x00) . $data;
        }

        return chr(0x02) . $this->encodeAsn1Length(strlen($data)) . $data;
    }

    private function encodeAsn1Sequence(string $data): string
    {
        return chr(0x30) . $this->encodeAsn1Length(strlen($data)) . $data;
    }
}
