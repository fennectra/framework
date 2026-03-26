<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('test', 'Run application tests [--filter=Name] [--unit] [--feature] [--coverage]')]
class TestCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $fennecDir = dirname(__DIR__, 2);
        $isApp = defined('FENNEC_BASE_PATH')
            && is_dir(FENNEC_BASE_PATH . '/app');

        $baseDir = $isApp ? FENNEC_BASE_PATH : $fennecDir;

        // Find PHPUnit binary
        $phpunitBin = $this->findPhpunit($fennecDir);

        if (!$phpunitBin) {
            echo "\033[31m✗ PHPUnit not found. Install it:\033[0m\n";
            echo "  composer require --dev phpunit/phpunit\n";

            return 1;
        }

        // Find config file
        $configFile = $this->findConfig($baseDir, $fennecDir, $isApp);

        if (!$configFile) {
            echo "\033[31m✗ No phpunit.xml found.\033[0m\n";
            echo "  Run \033[33m./forge make:test ExampleTest\033[0m to auto-create the test infrastructure.\n";

            return 1;
        }

        // Build command
        $cmd = $phpunitBin . ' --configuration=' . escapeshellarg($configFile);

        // Filter option
        if (isset($args['filter'])) {
            $cmd .= ' --filter=' . escapeshellarg($args['filter']);
        }

        // Suite selection
        if (isset($args['unit'])) {
            $cmd .= ' --testsuite=Unit';
        } elseif (isset($args['feature'])) {
            $cmd .= ' --testsuite=Feature';
        }

        // Coverage
        if (isset($args['coverage'])) {
            $cmd .= ' --coverage-text';
        }

        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   Running Tests                       ║\n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";

        $result = 0;
        passthru($cmd, $result);

        return $result;
    }

    private function findPhpunit(string $fennecDir): ?string
    {
        $php = PHP_BINARY;

        // Project vendor first
        if (defined('FENNEC_BASE_PATH')) {
            $path = FENNEC_BASE_PATH . '/vendor/bin/phpunit';
            if (file_exists($path) || file_exists("{$path}.bat")) {
                return escapeshellarg($php) . ' ' . escapeshellarg($path);
            }
        }

        // Framework vendor
        $path = "{$fennecDir}/vendor/bin/phpunit";
        if (file_exists($path) || file_exists("{$path}.bat")) {
            return escapeshellarg($php) . ' ' . escapeshellarg($path);
        }

        return null;
    }

    private function findConfig(string $baseDir, string $fennecDir, bool $isApp): ?string
    {
        // App mode: look in app directory only
        if ($isApp) {
            foreach ([
                $baseDir . '/phpunit.xml',
                $baseDir . '/phpunit.xml.dist',
                $baseDir . '/tests/phpunit.xml',
                $baseDir . '/tests/phpunit.xml.dist',
            ] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }

            return null;
        }

        // Framework mode
        $configPath = $fennecDir . '/config/phpunit.xml';

        return is_file($configPath) ? $configPath : null;
    }
}
