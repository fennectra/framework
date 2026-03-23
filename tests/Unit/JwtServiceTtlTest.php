<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Core\JwtService;
use PHPUnit\Framework\TestCase;

class JwtServiceTtlTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset Env state
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, true);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, ['SECRET_KEY' => 'test_secret_key_for_unit_tests_minimum_32_bytes!']);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);
    }

    public function testDefaultAccessTtlIs15Minutes(): void
    {
        $jwt = new JwtService();

        $this->assertSame(900, $jwt->getAccessTtl());
    }

    public function testDefaultRefreshTtlIs24Hours(): void
    {
        $jwt = new JwtService();

        $this->assertSame(86400, $jwt->getRefreshTtl());
    }

    public function testCustomAccessTtlFromEnv(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $vars = $ref->getProperty('vars');
        $current = $vars->getValue(null);
        $current['JWT_ACCESS_TTL'] = '3600';
        $vars->setValue(null, $current);

        $jwt = new JwtService();

        $this->assertSame(3600, $jwt->getAccessTtl());
    }

    public function testCustomRefreshTtlFromEnv(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $vars = $ref->getProperty('vars');
        $current = $vars->getValue(null);
        $current['JWT_REFRESH_TTL'] = '172800';
        $vars->setValue(null, $current);

        $jwt = new JwtService();

        $this->assertSame(172800, $jwt->getRefreshTtl());
    }

    public function testAccessTokenExpiresWithConfiguredTtl(): void
    {
        $jwt = new JwtService();
        $before = time();
        $result = $jwt->generateAccessToken('test@example.com');
        $after = time();

        // exp should be approximately now + 900 (15 min)
        $this->assertGreaterThanOrEqual($before + 900, $result['exp']);
        $this->assertLessThanOrEqual($after + 900, $result['exp']);
    }

    public function testAccessTokenWithCustomTtlOverride(): void
    {
        $jwt = new JwtService();
        $before = time();
        $result = $jwt->generateAccessToken('test@example.com', 60);

        $this->assertGreaterThanOrEqual($before + 60, $result['exp']);
        $this->assertLessThanOrEqual($before + 61, $result['exp']);
    }

    public function testRefreshTokenIsValid(): void
    {
        $jwt = new JwtService();
        $access = $jwt->generateAccessToken('test@example.com');
        $refresh = $jwt->generateRefreshToken('test@example.com', $access['rand']);

        $decoded = $jwt->decode($refresh);

        $this->assertNotNull($decoded);
        $this->assertSame('test@example.com', $decoded['sub']);
        $this->assertSame($access['rand'] . 'r', $decoded['rand']);
    }
}
