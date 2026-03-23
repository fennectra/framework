<?php

namespace Tests\Unit;

use Fennec\Core\Container;
use Fennec\Core\JwtService;
use PHPUnit\Framework\TestCase;

/**
 * Tests JWT authentication at the framework level.
 * Tests the JwtService encode/decode directly (not the app-level Auth middleware).
 */
class AuthTest extends TestCase
{
    private string $secret = 'test_secret_key_for_unit_tests_minimum_32_bytes!';
    private JwtService $jwt;

    protected function setUp(): void
    {
        $ref = new \ReflectionClass(\Fennec\Core\Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, true);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, ['SECRET_KEY' => $this->secret]);

        $container = new Container();
        $container->singleton(JwtService::class, fn () => new JwtService($this->secret));

        $this->jwt = new JwtService($this->secret);
    }

    public function testValidTokenReturnsPayload(): void
    {
        $payload = [
            'sub' => 'admin@test.com',
            'exp' => time() + 3600,
            'rand' => 'abc123',
        ];

        $token = $this->jwt->encode($payload);
        $result = $this->jwt->decode($token);

        $this->assertNotNull($result);
        $this->assertEquals('admin@test.com', $result['sub']);
        $this->assertArrayHasKey('exp', $result);
        $this->assertArrayHasKey('rand', $result);
    }

    public function testExpiredTokenReturnsNull(): void
    {
        $payload = [
            'sub' => 'admin@test.com',
            'exp' => time() - 100,
            'rand' => 'abc123',
        ];

        $token = $this->jwt->encode($payload);
        $result = $this->jwt->decode($token);

        $this->assertNull($result);
    }

    public function testInvalidSignatureReturnsNull(): void
    {
        $otherJwt = new JwtService('completely_different_secret_key_here!');
        $token = $otherJwt->encode([
            'sub' => 'admin@test.com',
            'exp' => time() + 3600,
        ]);

        $result = $this->jwt->decode($token);

        $this->assertNull($result);
    }

    public function testMalformedTokenReturnsNull(): void
    {
        $result = $this->jwt->decode('not.a.valid.jwt.token');

        $this->assertNull($result);
    }

    public function testEmptyTokenReturnsNull(): void
    {
        $result = $this->jwt->decode('');

        $this->assertNull($result);
    }

    public function testGenerateAccessTokenContainsClaims(): void
    {
        $accessToken = $this->jwt->generateAccessToken('user@test.com');

        $this->assertArrayHasKey('token', $accessToken);
        $this->assertArrayHasKey('exp', $accessToken);
        $this->assertArrayHasKey('rand', $accessToken);

        $decoded = $this->jwt->decode($accessToken['token']);
        $this->assertNotNull($decoded);
        $this->assertEquals('user@test.com', $decoded['sub']);
    }

    public function testGenerateRefreshTokenIsValid(): void
    {
        $accessToken = $this->jwt->generateAccessToken('user@test.com');
        $refreshToken = $this->jwt->generateRefreshToken('user@test.com', $accessToken['rand']);

        $this->assertIsString($refreshToken);

        $decoded = $this->jwt->decode($refreshToken);
        $this->assertNotNull($decoded);
        $this->assertEquals('user@test.com', $decoded['sub']);
    }

    public function testTokenTtlIsConfigurable(): void
    {
        $accessToken = $this->jwt->generateAccessToken('user@test.com', 60);

        $decoded = $this->jwt->decode($accessToken['token']);
        $this->assertNotNull($decoded);
        $this->assertLessThanOrEqual(time() + 61, $decoded['exp']);
    }
}
