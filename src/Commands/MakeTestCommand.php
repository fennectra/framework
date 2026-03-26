<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:test', 'Create a new test class [--unit] [--feature]')]
class MakeTestCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:test <Name> [--unit] [--feature]\033[0m\n";
            echo "  Examples:\n";
            echo "    ./forge make:test UserService          → tests/Unit/UserServiceTest.php\n";
            echo "    ./forge make:test Auth/Login --feature  → tests/Feature/Auth/LoginTest.php\n";

            return 1;
        }

        $isFeature = isset($args['feature']);
        $isUnit = isset($args['unit']) || !$isFeature;
        $type = $isFeature ? 'Feature' : 'Unit';

        // Add Test suffix if missing
        if (!str_ends_with($name, 'Test')) {
            $name .= 'Test';
        }

        // Support subdirectory via name: Auth/LoginTest
        $subdomain = '';
        $className = $name;

        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $subdomain = implode('/', $parts);
        }

        $namespace = $subdomain ? "Tests\\{$type}\\{$subdomain}" : "Tests\\{$type}";
        $dir = FENNEC_BASE_PATH . "/tests/{$type}" . ($subdomain ? "/{$subdomain}" : '');
        $file = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            echo "\033[31mTest {$className} already exists.\033[0m\n";

            return 1;
        }

        // Init test infrastructure if missing
        $this->ensureTestInfrastructure();

        $nsEscaped = str_replace('/', '\\', $namespace);

        if ($isFeature) {
            $content = $this->featureTemplate($nsEscaped, $className);
        } else {
            $content = $this->unitTemplate($nsEscaped, $className);
        }

        file_put_contents($file, $content);

        $relativePath = "tests/{$type}" . ($subdomain ? "/{$subdomain}" : '') . "/{$className}.php";
        echo "\033[32m✓ Test created: {$relativePath}\033[0m\n";
        echo "\n  \033[33mRun:\033[0m\n";
        echo "    ./forge test                    # Run all tests\n";
        echo "    ./forge test --filter={$className}  # Run this test only\n";

        return 0;
    }

    private function unitTemplate(string $namespace, string $className): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use PHPUnit\\Framework\\TestCase;

class {$className} extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testExample(): void
    {
        \$this->assertTrue(true);
    }
}

PHP;
    }

    private function featureTemplate(string $namespace, string $className): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Tests\\TestCase;

class {$className} extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testExample(): void
    {
        \$this->assertTrue(true);
    }
}

PHP;
    }

    private function ensureTestInfrastructure(): void
    {
        $base = FENNEC_BASE_PATH;

        // Create phpunit.xml if missing
        if (!file_exists("{$base}/phpunit.xml") && !file_exists("{$base}/phpunit.xml.dist")) {
            $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
XML;
            file_put_contents("{$base}/phpunit.xml", $xml . "\n");
            echo "\033[32m✓ Created phpunit.xml\033[0m\n";
        }

        // Create TestCase if missing
        $testCaseFile = "{$base}/tests/TestCase.php";
        if (!file_exists($testCaseFile)) {
            if (!is_dir("{$base}/tests")) {
                mkdir("{$base}/tests", 0755, true);
            }

            $content = <<<'PHP'
<?php

namespace Tests;

use Fennec\Core\DB;
use Fennec\Core\Env;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load .env if exists
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $ref = new \ReflectionClass(Env::class);
            $loaded = $ref->getProperty('loaded');
            $loaded->setValue(null, false);
            Env::load($envFile);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset DB connections between tests
        if (method_exists(DB::class, 'resetManager')) {
            DB::resetManager();
        }
    }

    /**
     * Run a raw SQL query and return all rows.
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = DB::raw($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Run a raw SQL query and return the first row.
     */
    protected function queryOne(string $sql, array $params = []): ?array
    {
        $rows = $this->query($sql, $params);

        return $rows[0] ?? null;
    }
}
PHP;
            file_put_contents($testCaseFile, $content . "\n");
            echo "\033[32m✓ Created tests/TestCase.php\033[0m\n";
        }

        // Create directories
        foreach (['tests/Unit', 'tests/Feature'] as $dir) {
            if (!is_dir("{$base}/{$dir}")) {
                mkdir("{$base}/{$dir}", 0755, true);
            }
        }
    }
}
