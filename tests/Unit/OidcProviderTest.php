<?php

namespace Tests\Unit;

use Fennec\Core\OAuth\OAuthToken;
use Fennec\Core\OAuth\OAuthUser;
use Fennec\Core\OAuth\OidcClaims;
use Fennec\Core\OAuth\OidcProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the OIDC provider abstract class.
 * Uses a concrete test subclass to test the abstract methods.
 */
class OidcProviderTest extends TestCase
{
    private \OpenSSLAsymmetricKey $privateKey;
    private \OpenSSLAsymmetricKey $publicKey;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        // Generate an RSA key pair for testing
        $config = $this->opensslConfig();
        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            $this->markTestSkipped('OpenSSL key generation not available: ' . openssl_error_string());
        }
        $this->privateKey = $keyPair;

        $details = openssl_pkey_get_details($keyPair);
        $this->publicKeyPem = $details['key'];
        $this->publicKey = openssl_pkey_get_public($this->publicKeyPem);
    }

    /**
     * @return array<string, mixed>
     */
    private function opensslConfig(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Windows needs explicit openssl.cnf path
        if (PHP_OS_FAMILY === 'Windows') {
            $iniDir = dirname((string) php_ini_loaded_file());
            $cnf = $iniDir . '/extras/ssl/openssl.cnf';
            if (is_file($cnf)) {
                $config['config'] = $cnf;
            }
        }

        return $config;
    }

    public function testOidcClaimsFromArray(): void
    {
        $claims = OidcClaims::fromArray([
            'sub' => '12345',
            'email' => 'user@example.com',
            'email_verified' => true,
            'name' => 'John Doe',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'picture' => 'https://example.com/photo.jpg',
            'locale' => 'fr',
            'nonce' => 'abc123',
            'iss' => 'https://idp.example.com',
            'aud' => 'my-client-id',
            'iat' => 1000000,
            'exp' => 2000000,
        ]);

        $this->assertSame('12345', $claims->sub);
        $this->assertSame('user@example.com', $claims->email);
        $this->assertTrue($claims->emailVerified);
        $this->assertSame('John Doe', $claims->name);
        $this->assertSame('John', $claims->givenName);
        $this->assertSame('Doe', $claims->familyName);
        $this->assertSame('https://example.com/photo.jpg', $claims->picture);
        $this->assertSame('fr', $claims->locale);
        $this->assertSame('abc123', $claims->nonce);
        $this->assertSame('https://idp.example.com', $claims->issuer);
        $this->assertSame('my-client-id', $claims->audience);
        $this->assertSame(1000000, $claims->issuedAt);
        $this->assertSame(2000000, $claims->expiresAt);
    }

    public function testOidcClaimsFromArrayWithArrayAudience(): void
    {
        $claims = OidcClaims::fromArray([
            'sub' => '123',
            'aud' => ['client-a', 'client-b'],
        ]);

        $this->assertSame('client-a', $claims->audience);
    }

    public function testOidcClaimsFromArrayWithMissingFields(): void
    {
        $claims = OidcClaims::fromArray([
            'sub' => '123',
        ]);

        $this->assertSame('123', $claims->sub);
        $this->assertNull($claims->email);
        $this->assertNull($claims->name);
        $this->assertNull($claims->nonce);
    }

    public function testOidcClaimsToOAuthUser(): void
    {
        $claims = OidcClaims::fromArray([
            'sub' => '12345',
            'email' => 'user@example.com',
            'name' => 'John Doe',
            'picture' => 'https://example.com/photo.jpg',
        ]);

        $user = $claims->toOAuthUser('my-provider');

        $this->assertInstanceOf(OAuthUser::class, $user);
        $this->assertSame('12345', $user->id);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('https://example.com/photo.jpg', $user->avatar);
        $this->assertSame('my-provider', $user->provider);
    }

    public function testOAuthTokenSupportsIdToken(): void
    {
        $token = new OAuthToken(
            accessToken: 'access123',
            refreshToken: 'refresh456',
            expiresIn: 3600,
            tokenType: 'Bearer',
            idToken: 'eyJ.abc.xyz',
        );

        $this->assertSame('eyJ.abc.xyz', $token->idToken);
    }

    public function testOAuthTokenIdTokenDefaultsToNull(): void
    {
        $token = new OAuthToken(accessToken: 'access123');

        $this->assertNull($token->idToken);
    }

    public function testPkceCodeVerifierLength(): void
    {
        $provider = $this->createTestProvider();
        $verifier = $provider->generateCodeVerifier();

        // Base64url-encoded 32 bytes = 43 characters
        $this->assertSame(43, strlen($verifier));
        // Should only contain URL-safe characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
    }

    public function testPkceCodeChallengeIsDeterministic(): void
    {
        $provider = $this->createTestProvider();
        $verifier = 'test-verifier-string-for-pkce';

        $challenge1 = $provider->generateCodeChallenge($verifier);
        $challenge2 = $provider->generateCodeChallenge($verifier);

        $this->assertSame($challenge1, $challenge2);
    }

    public function testPkceCodeChallengeIsSha256OfVerifier(): void
    {
        $provider = $this->createTestProvider();
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

        $challenge = $provider->generateCodeChallenge($verifier);
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->assertSame($expected, $challenge);
    }

    public function testValidateIdTokenRejectsInvalidFormat(): void
    {
        $provider = $this->createTestProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid id_token format');

        $provider->validateIdToken('not-a-jwt');
    }

    public function testValidateIdTokenRejectsInvalidHeader(): void
    {
        $provider = $this->createTestProvider();

        // Build a token with invalid base64 header
        $this->expectException(\RuntimeException::class);

        $provider->validateIdToken('!!!.payload.signature');
    }

    public function testValidateClaimsRejectsExpiredToken(): void
    {
        $provider = $this->createTestProvider();

        // Create a properly signed but expired token
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '123',
            'iss' => 'https://idp.example.com',
            'aud' => 'test-client-id',
            'exp' => time() - 3600, // expired 1 hour ago
            'iat' => time() - 7200,
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('id_token has expired');

        $provider->validateIdToken($token);
    }

    public function testValidateClaimsRejectsWrongIssuer(): void
    {
        $provider = $this->createTestProvider();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '123',
            'iss' => 'https://wrong-issuer.com',
            'aud' => 'test-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('id_token issuer mismatch');

        $provider->validateIdToken($token);
    }

    public function testValidateClaimsRejectsWrongAudience(): void
    {
        $provider = $this->createTestProvider();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '123',
            'iss' => 'https://idp.example.com',
            'aud' => 'wrong-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('id_token audience does not contain client_id');

        $provider->validateIdToken($token);
    }

    public function testValidateClaimsRejectsWrongNonce(): void
    {
        $provider = $this->createTestProvider();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '123',
            'iss' => 'https://idp.example.com',
            'aud' => 'test-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'token-nonce',
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('id_token nonce mismatch');

        $provider->validateIdToken($token, 'expected-nonce');
    }

    public function testValidateIdTokenWithValidSignature(): void
    {
        $provider = $this->createTestProvider();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => 'user-42',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'iss' => 'https://idp.example.com',
            'aud' => 'test-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'my-nonce',
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $claims = $provider->validateIdToken($token, 'my-nonce');

        $this->assertInstanceOf(OidcClaims::class, $claims);
        $this->assertSame('user-42', $claims->sub);
        $this->assertSame('test@example.com', $claims->email);
        $this->assertSame('Test User', $claims->name);
        $this->assertSame('my-nonce', $claims->nonce);
    }

    public function testValidateIdTokenRejectsInvalidSignature(): void
    {
        $provider = $this->createTestProvider();

        // Sign with a different key
        $otherKey = openssl_pkey_new($this->opensslConfig());

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '123',
            'iss' => 'https://idp.example.com',
            'aud' => 'test-client-id',
            'exp' => time() + 3600,
        ]));

        $data = $header . '.' . $payload;
        openssl_sign($data, $signature, $otherKey, OPENSSL_ALGO_SHA256);
        $sig = $this->base64UrlEncode($signature);

        $token = $data . '.' . $sig;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('signature verification failed');

        $provider->validateIdToken($token);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createTestProvider(): TestableOidcProvider
    {
        $details = openssl_pkey_get_details($this->privateKey);

        // Build JWK from public key components
        $jwk = [
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => 'test-key-1',
        ];

        return new TestableOidcProvider(
            issuer: 'https://idp.example.com',
            clientId: 'test-client-id',
            clientSecret: 'test-secret',
            redirectUri: 'https://app.example.com/callback',
            jwks: ['keys' => [$jwk]],
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

/**
 * Testable OIDC provider that bypasses HTTP calls.
 */
class TestableOidcProvider extends OidcProvider
{
    private string $issuer;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    /** @var array<string, mixed> */
    private array $jwks;

    public function __construct(
        string $issuer,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        array $jwks,
    ) {
        $this->issuer = $issuer;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->jwks = $jwks;
    }

    protected function getIssuer(): string
    {
        return $this->issuer;
    }

    protected function getClientId(): string
    {
        return $this->clientId;
    }

    protected function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    protected function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    protected function getProviderName(): string
    {
        return 'test-oidc';
    }

    /**
     * Override to return static JWKS without HTTP call.
     */
    protected function fetchJwks(): array
    {
        return $this->jwks;
    }

    /**
     * Override discover to return minimal discovery doc.
     */
    public function discover(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->issuer . '/authorize',
            'token_endpoint' => $this->issuer . '/token',
            'userinfo_endpoint' => $this->issuer . '/userinfo',
            'jwks_uri' => $this->issuer . '/.well-known/jwks.json',
        ];
    }
}
