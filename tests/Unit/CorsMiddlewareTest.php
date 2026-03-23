<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset Env state
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);
    }

    public function testResolveOriginAllowsInDevMode(): void
    {
        $this->setEnv(['APP_ENV' => 'dev']);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $result = $method->invoke($middleware, 'http://any-origin.com');

        $this->assertSame('http://any-origin.com', $result);
    }

    public function testResolveOriginBlocksInProdWithoutConfig(): void
    {
        $this->setEnv(['APP_ENV' => 'prod', 'CORS_ALLOWED_ORIGINS' => '']);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $result = $method->invoke($middleware, 'http://evil.com');

        $this->assertNull($result);
    }

    public function testResolveOriginAllowsWhitelistedOrigin(): void
    {
        $this->setEnv([
            'APP_ENV' => 'prod',
            'CORS_ALLOWED_ORIGINS' => 'https://app.example.com,https://admin.example.com',
        ]);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $this->assertSame('https://app.example.com', $method->invoke($middleware, 'https://app.example.com'));
        $this->assertSame('https://admin.example.com', $method->invoke($middleware, 'https://admin.example.com'));
    }

    public function testResolveOriginBlocksNonWhitelistedOrigin(): void
    {
        $this->setEnv([
            'APP_ENV' => 'prod',
            'CORS_ALLOWED_ORIGINS' => 'https://app.example.com',
        ]);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $this->assertNull($method->invoke($middleware, 'https://evil.com'));
    }

    public function testResolveOriginReturnsNullForEmptyOrigin(): void
    {
        $this->setEnv(['APP_ENV' => 'prod']);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $this->assertNull($method->invoke($middleware, ''));
    }

    public function testResolveOriginAllowsWildcardInProd(): void
    {
        $this->setEnv(['APP_ENV' => 'prod', 'CORS_ALLOWED_ORIGINS' => '*']);

        $middleware = new CorsMiddleware();
        $method = new \ReflectionMethod($middleware, 'resolveOrigin');

        $this->assertSame('http://any.com', $method->invoke($middleware, 'http://any.com'));
    }

    private function setEnv(array $vars): void
    {
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, true);
        $varsProp = $ref->getProperty('vars');
        $varsProp->setValue(null, $vars);
    }
}
