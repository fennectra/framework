<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('quality', 'Check code quality: types, style, tests [--fix] [--framework]')]
class QualityCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $fix = isset($args['fix']);
        $forceFramework = isset($args['framework']);
        $fennecDir = dirname(__DIR__, 2);
        $exitCode = 0;

        // Detect context: app or framework?
        $isApp = defined('FENNEC_BASE_PATH')
            && is_dir(FENNEC_BASE_PATH . '/app')
            && !$forceFramework;

        if ($isApp) {
            $targetDir = FENNEC_BASE_PATH . '/app';
            $testDir = FENNEC_BASE_PATH . '/tests';
            $label = 'Application';
        } else {
            $targetDir = $fennecDir . '/src';
            $testDir = $fennecDir . '/tests';
            $label = 'Framework';
        }

        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   Code Quality Check                 ║\n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";
        echo "  \033[90mTarget: {$label} ({$targetDir})\033[0m\n\n";

        // 1. PHP-CS-Fixer — Style & indentation
        echo "  \033[1m[1/3] PHP-CS-Fixer — Style & indentation\033[0m\n";
        $csFixerBin = $this->findBin($fennecDir);

        if ($csFixerBin) {
            if ($isApp) {
                // App mode: run CS-Fixer directly on app/ directory
                $csCmd = $fix
                    ? "{$csFixerBin} fix " . escapeshellarg($targetDir) . ' --rules=@PSR12'
                    : "{$csFixerBin} check " . escapeshellarg($targetDir) . ' --rules=@PSR12 --diff';
            } else {
                // Framework mode: use config file
                $configDir = $fennecDir . '/config';
                $csCmd = $fix
                    ? "{$csFixerBin} fix --config=" . escapeshellarg("{$configDir}/.php-cs-fixer.php")
                    : "{$csFixerBin} check --config=" . escapeshellarg("{$configDir}/.php-cs-fixer.php") . ' --diff';
            }

            $csResult = 0;
            passthru($csCmd, $csResult);

            if ($csResult === 0) {
                echo "  \033[32m✓ Style OK\033[0m\n\n";
            } elseif ($fix) {
                echo "  \033[33m✓ Style fixed\033[0m\n\n";
            } else {
                echo "  \033[31m✗ Style issues detected (run with --fix to auto-correct)\033[0m\n\n";
                $exitCode = 1;
            }
        } else {
            echo "  \033[33m⚠ php-cs-fixer not found, skipped\033[0m\n\n";
        }

        // 2. PHPStan — Static analysis
        echo "  \033[1m[2/3] PHPStan — Static analysis\033[0m\n";
        $phpstanBin = $this->findBin($fennecDir, 'phpstan');

        if ($phpstanBin) {
            if ($isApp) {
                // App mode: use project phpstan.neon if exists, otherwise analyse with Routes excluded
                $projectNeon = FENNEC_BASE_PATH . '/phpstan.neon';
                if (file_exists($projectNeon)) {
                    $stanCmd = "{$phpstanBin} analyse --configuration=" . escapeshellarg($projectNeon) . ' --no-progress';
                } else {
                    // Analyse app/ at level 5, excluding Routes/ ($router is injected at runtime)
                    $stanCmd = "{$phpstanBin} analyse " . escapeshellarg($targetDir)
                        . ' --level=5 --no-progress'
                        . ' --exclude=' . escapeshellarg($targetDir . '/Routes');
                }
            } else {
                // Framework mode: use config file
                $configDir = $fennecDir . '/config';
                $stanCmd = "{$phpstanBin} analyse --configuration=" . escapeshellarg("{$configDir}/phpstan.neon") . ' --no-progress';
            }

            $stanResult = 0;
            passthru($stanCmd, $stanResult);

            if ($stanResult === 0) {
                echo "  \033[32m✓ Static analysis OK\033[0m\n\n";
            } else {
                echo "  \033[31m✗ Errors detected by PHPStan\033[0m\n\n";
                $exitCode = 1;
            }
        } else {
            echo "  \033[33m⚠ phpstan not found, skipped\033[0m\n\n";
        }

        // 3. PHPUnit — Tests
        echo "  \033[1m[3/3] PHPUnit — Tests\033[0m\n";
        $phpunitBin = $this->findBin($fennecDir, 'phpunit');

        if ($phpunitBin) {
            if ($isApp && is_dir($testDir) && (is_file("{$testDir}/phpunit.xml") || is_file("{$testDir}/phpunit.xml.dist"))) {
                // App mode: use app's test config
                $configFile = is_file("{$testDir}/phpunit.xml") ? "{$testDir}/phpunit.xml" : "{$testDir}/phpunit.xml.dist";
                $testCmd = "{$phpunitBin} --configuration=" . escapeshellarg($configFile);
            } elseif ($isApp) {
                // App mode without test config: check for root-level phpunit.xml
                $rootConfig = FENNEC_BASE_PATH . '/phpunit.xml';
                $rootConfigDist = FENNEC_BASE_PATH . '/phpunit.xml.dist';
                if (is_file($rootConfig) || is_file($rootConfigDist)) {
                    $configFile = is_file($rootConfig) ? $rootConfig : $rootConfigDist;
                    $testCmd = "{$phpunitBin} --configuration=" . escapeshellarg($configFile);
                } else {
                    echo "  \033[33m⚠ No test configuration found (create tests/phpunit.xml), skipped\033[0m\n\n";
                    $testCmd = null;
                }
            } else {
                // Framework mode: use framework config
                $configDir = $fennecDir . '/config';
                $testCmd = "{$phpunitBin} --configuration=" . escapeshellarg("{$configDir}/phpunit.xml");
            }

            if ($testCmd !== null) {
                $testResult = 0;
                passthru($testCmd, $testResult);

                if ($testResult === 0) {
                    echo "  \033[32m✓ Tests OK\033[0m\n\n";
                } else {
                    echo "  \033[31m✗ Tests failed\033[0m\n\n";
                    $exitCode = 1;
                }
            }
        } else {
            echo "  \033[33m⚠ phpunit not found, skipped\033[0m\n\n";
        }

        // Summary
        echo "  ─────────────────────────────────────\n";
        if ($exitCode === 0) {
            echo "  \033[1;32m✓ Quality OK — ready for deployment\033[0m\n\n";
        } else {
            echo "  \033[1;31m✗ Issues detected\033[0m\n\n";
        }

        return $exitCode;
    }

    private function findBin(string $fennecDir, string $name = 'php-cs-fixer'): ?string
    {
        $php = PHP_BINARY;

        // Search in framework vendor (local dev)
        $path = "{$fennecDir}/vendor/bin/{$name}";
        if (file_exists($path) || file_exists("{$path}.bat")) {
            return escapeshellarg($php) . ' ' . escapeshellarg($path);
        }

        // Search in project vendor (installed via Composer)
        if (defined('FENNEC_BASE_PATH')) {
            $projectPath = FENNEC_BASE_PATH . "/vendor/bin/{$name}";
            if (file_exists($projectPath) || file_exists("{$projectPath}.bat")) {
                return escapeshellarg($php) . ' ' . escapeshellarg($projectPath);
            }
        }

        return null;
    }
}
