<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $tmpEnvFile;

    protected function setUp(): void
    {
        $this->tmpEnvFile = sys_get_temp_dir() . '/test_env_' . uniqid();
        file_put_contents($this->tmpEnvFile, implode("\n", [
            '# Comment line',
            'APP_NAME="TestApp"',
            'APP_DEBUG=true',
            "APP_KEY='secret123'",
            '',
            'DB_HOST=localhost',
        ]));
    }

    public function testLoadsEnvFile(): void
    {
        // Forcer le rechargement en utilisant la réflexion
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);

        Env::load($this->tmpEnvFile);

        $this->assertEquals('TestApp', Env::get('APP_NAME'));
        $this->assertEquals('true', Env::get('APP_DEBUG'));
        $this->assertEquals('secret123', Env::get('APP_KEY'));
        $this->assertEquals('localhost', Env::get('DB_HOST'));
    }

    public function testReturnsDefaultWhenKeyMissing(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);

        Env::load($this->tmpEnvFile);

        $this->assertEquals('default_val', Env::get('NON_EXISTENT', 'default_val'));
    }

    public function testIgnoresCommentLines(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);

        Env::load($this->tmpEnvFile);

        // "# Comment line" ne doit pas être parsée
        $this->assertEquals('', Env::get('# Comment line'));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpEnvFile)) {
            unlink($this->tmpEnvFile);
        }

        // Reset state
        $ref = new \ReflectionClass(Env::class);
        $loaded = $ref->getProperty('loaded');
        $loaded->setValue(null, false);
        $vars = $ref->getProperty('vars');
        $vars->setValue(null, []);
    }
}
