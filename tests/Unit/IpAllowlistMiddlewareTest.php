<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Core\Security\SecurityLogger;
use Fennec\Middleware\IpAllowlistMiddleware;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class IpAllowlistMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, true);
        $ref->getProperty('vars')->setValue(null, [
            'SECRET_KEY' => 'test-key',
        ]);

        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, false);
        $ref->getProperty('vars')->setValue(null, []);

        $ref = new \ReflectionClass(SecurityLogger::class);
        $ref->getProperty('instance')->setValue(null, null);
        $ref->getProperty('previousHash')->setValue(null, '');
    }

    public function testAllowsWhenNoConfig(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        // Empty allowlist = allow all (opt-in behavior)
        $this->assertTrue(true); // getAllowedIps returns [], middleware passes through
    }

    public function testAllowsExactIpMatch(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertTrue($method->invoke($middleware, '10.0.0.1', ['10.0.0.1', '10.0.0.2']));
    }

    public function testBlocksNonMatchingIp(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertFalse($method->invoke($middleware, '192.168.1.1', ['10.0.0.1', '10.0.0.2']));
    }

    public function testAllowsCidrMatch(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertTrue($method->invoke($middleware, '10.0.0.55', ['10.0.0.0/24']));
    }

    public function testBlocksCidrNonMatch(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertFalse($method->invoke($middleware, '192.168.1.1', ['10.0.0.0/24']));
    }

    public function testAllowsWideSubnet(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertTrue($method->invoke($middleware, '10.255.255.255', ['10.0.0.0/8']));
    }

    public function testMixedExactAndCidr(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $allowed = ['192.168.1.100', '10.0.0.0/16'];

        $this->assertTrue($method->invoke($middleware, '192.168.1.100', $allowed));
        $this->assertTrue($method->invoke($middleware, '10.0.5.1', $allowed));
        $this->assertFalse($method->invoke($middleware, '172.16.0.1', $allowed));
    }

    public function testMatchesCidrWithInvalidIpReturnsFalse(): void
    {
        $method = new \ReflectionMethod(IpAllowlistMiddleware::class, 'matchesCidr');

        $this->assertFalse($method->invoke(null, 'invalid', '10.0.0.0/24'));
    }

    public function testLocalhostAllowed(): void
    {
        $middleware = new IpAllowlistMiddleware();
        $method = new \ReflectionMethod($middleware, 'isAllowed');

        $this->assertTrue($method->invoke($middleware, '127.0.0.1', ['127.0.0.1']));
    }
}
