<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Cli\CommandRunner;
use Fennec\Core\Env;

#[Command('test:integration', 'Generate a temp app, run all make:* commands, migrate, seed, and run integration tests')]
class TestIntegrationCommand implements CommandInterface
{
    private string $tempDir;
    private int $exitCode = 0;

    public function execute(array $args): int
    {
        $fennecDir = dirname(__DIR__, 2);
        $this->tempDir = $fennecDir . '/tests/Integration/.skeleton_temp';
        $keepTemp = isset($args['keep']);

        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════════════╗\n";
        echo "  ║   Integration Test Suite                      ║\n";
        echo "  ╚══════════════════════════════════════════════╝\n";
        echo "\033[0m\n";

        // 1. Create temp skeleton
        $this->step('1/6', 'Creating temp skeleton', fn () => $this->createSkeleton());

        // 2. Run all make:* commands (in-process via CommandRunner)
        $this->step('2/6', 'Running make:* commands', fn () => $this->runMakeCommands());

        // 3. Migrate
        $this->step('3/6', 'Running migrations', fn () => $this->runCommand('migrate'));

        // 4. Seed
        $this->step('4/6', 'Seeding database', fn () => $this->runCommand('db:seed', ['class' => 'AuthSeeder']));

        // 5. Run integration tests
        $this->step('5/6', 'Running integration tests', fn () => $this->runTests());

        // 6. Cleanup
        if (!$keepTemp) {
            $this->step('6/6', 'Cleanup', fn () => $this->cleanup());
        } else {
            echo "  \033[1m[6/6] Cleanup\033[0m\n";
            echo "    \033[33m⚠\033[0m Skipped (--keep flag)\n\n";
        }

        echo "\n  ─────────────────────────────────────\n";
        if ($this->exitCode === 0) {
            echo "  \033[1;32m✓ All integration tests passed\033[0m\n\n";
        } else {
            echo "  \033[1;31m✗ Integration tests failed\033[0m\n\n";
        }

        return $this->exitCode;
    }

    private function step(string $num, string $label, callable $fn): void
    {
        echo "  \033[1m[{$num}] {$label}\033[0m\n";
        $fn();
        echo "\n";
    }

    private function createSkeleton(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }

        $dirs = [
            'app/Controllers', 'app/Models', 'app/Dto', 'app/Middleware',
            'app/Routes', 'app/Mail', 'app/Commands',
            'database/migrations', 'database/seeders',
            'var/cache', 'var/logs', 'var/lockout',
            'public', 'storage',
        ];

        foreach ($dirs as $dir) {
            mkdir($this->tempDir . '/' . $dir, 0755, true);
        }

        // Create SQLite DB file
        touch($this->tempDir . '/var/database.sqlite');

        // Create .env
        file_put_contents($this->tempDir . '/.env', implode("\n", [
            'APP_ENV=dev',
            'APP_NAME=IntegrationTest',
            'DB_DRIVER=sqlite',
            'SQLITE_DB=' . $this->tempDir . '/var/database.sqlite',
            'SECRET_KEY=' . base64_encode(random_bytes(32)),
            'EVENT_BROKER=sync',
            'STORAGE_DRIVER=local',
        ]));

        // Minimal route file
        file_put_contents($this->tempDir . '/app/Routes/public.php', "<?php\n");
        file_put_contents($this->tempDir . '/public/index.php', "<?php\n");

        echo "    \033[32m✓\033[0m Skeleton created\n";
    }

    private function runMakeCommands(): void
    {
        $commands = [
            'make:auth',
            'make:organization',
            'make:email',
            'make:audit',
            'make:nf525',
            'make:rgpd',
            'make:webhook',
        ];

        // Save and override FENNEC_BASE_PATH
        $originalBasePath = defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : null;

        // We can't redefine a constant, so we use a subprocess
        $fennecDir = dirname(__DIR__, 2);
        $php = escapeshellarg(PHP_BINARY);
        $cli = $fennecDir . '/bin/cli';

        foreach ($commands as $cmd) {
            echo "    Running {$cmd}... ";

            // Create a small PHP script that sets FENNEC_BASE_PATH and runs the command
            $script = $this->tempDir . '/var/cache/_run_cmd.php';
            $escapedTemp = addslashes($this->tempDir);
            $escapedCli = addslashes($cli);

            file_put_contents($script, "<?php\n"
                . "define('FENNEC_BASE_PATH', '{$escapedTemp}');\n"
                . "require_once '" . addslashes($fennecDir) . "/vendor/autoload.php';\n"
                . "\$container = new \\Fennec\\Core\\Container();\n"
                . "\$runner = new \\Fennec\\Core\\Cli\\CommandRunner(\$container);\n"
                . "\$runner->discoverCommands('" . addslashes($fennecDir) . "/src/Commands');\n"
                . "exit(\$runner->run(['cli', '{$cmd}']));\n");

            $output = [];
            exec("{$php} " . escapeshellarg($script) . ' 2>&1', $output, $result);

            if ($result === 0) {
                $generated = 0;
                foreach ($output as $line) {
                    if (str_contains($line, '✓')) {
                        $generated++;
                    }
                }
                echo "\033[32m✓\033[0m ({$generated} files)\n";
            } else {
                echo "\033[31m✗\033[0m failed\n";
                $this->exitCode = 1;
            }
        }

        @unlink($this->tempDir . '/var/cache/_run_cmd.php');
    }

    private function runCommand(string $cmd, array $extraArgs = []): void
    {
        $fennecDir = dirname(__DIR__, 2);
        $php = escapeshellarg(PHP_BINARY);

        $script = $this->tempDir . '/var/cache/_run_cmd.php';
        $escapedTemp = addslashes($this->tempDir);

        $argsCode = '';
        if (!empty($extraArgs)) {
            $parts = ["'cli'", "'{$cmd}'"];
            foreach ($extraArgs as $k => $v) {
                $parts[] = "'--{$k}={$v}'";
            }
            $argsCode = '[' . implode(', ', $parts) . ']';
        } else {
            $argsCode = "['cli', '{$cmd}']";
        }

        file_put_contents($script, "<?php\n"
            . "define('FENNEC_BASE_PATH', '{$escapedTemp}');\n"
            . "require_once '" . addslashes($fennecDir) . "/vendor/autoload.php';\n"
            . "try { \\Fennec\\Core\\DB::raw('PRAGMA journal_mode=WAL'); } catch (\\Throwable \$e) {}\n"
            . "\$container = new \\Fennec\\Core\\Container();\n"
            . "\$runner = new \\Fennec\\Core\\Cli\\CommandRunner(\$container);\n"
            . "\$runner->discoverCommands('" . addslashes($fennecDir) . "/src/Commands');\n"
            . "exit(\$runner->run({$argsCode}));\n");

        $output = [];
        exec("{$php} " . escapeshellarg($script) . ' 2>&1', $output, $result);

        $count = 0;
        foreach ($output as $line) {
            if (str_contains($line, '✓')) {
                $count++;
            }
        }

        if ($result === 0) {
            echo "    \033[32m✓\033[0m {$cmd} done" . ($count > 0 ? " ({$count} items)" : '') . "\n";
        } else {
            echo "    \033[31m✗\033[0m {$cmd} failed\n";
            foreach ($output as $line) {
                if (str_contains($line, 'error') || str_contains($line, 'Error') || str_contains($line, '✗')) {
                    echo "      {$line}\n";
                }
            }
            $this->exitCode = 1;
        }

        @unlink($script);
    }

    private function runTests(): void
    {
        $fennecDir = dirname(__DIR__, 2);
        $php = PHP_BINARY;

        $phpunit = $fennecDir . '/vendor/bin/phpunit';
        if (!file_exists($phpunit) && !file_exists($phpunit . '.bat')) {
            echo "    \033[31m✗\033[0m PHPUnit not found\n";
            $this->exitCode = 1;

            return;
        }

        putenv('FENNEC_INTEGRATION_TEMP=' . $this->tempDir);
        putenv('DB_DRIVER=sqlite');
        putenv('SQLITE_DB=' . $this->tempDir . '/var/database.sqlite');

        $configFile = $fennecDir . '/config/phpunit.xml';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($phpunit)
            . ' --configuration=' . escapeshellarg($configFile)
            . ' --testsuite=Integration 2>&1';

        $result = 0;
        passthru($cmd, $result);

        if ($result !== 0) {
            $this->exitCode = 1;
        }
    }

    private function cleanup(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
            echo "    \033[32m✓\033[0m Temp skeleton removed\n";
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
