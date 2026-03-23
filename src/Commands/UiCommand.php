<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('ui', 'Start the Fennec UI dashboard [--build] [--sync] [--port=3001]')]
class UiCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $uiDir = dirname(__DIR__, 2) . '/ui';

        if (!is_dir($uiDir)) {
            echo "\033[31m✗ UI directory not found: {$uiDir}\033[0m\n";

            return 1;
        }

        if (isset($args['sync'])) {
            return $this->sync($uiDir);
        }

        if (isset($args['build'])) {
            return $this->build($uiDir);
        }

        return $this->dev($uiDir, $args['port'] ?? '3001');
    }

    private function dev(string $uiDir, string $port): int
    {
        $this->ensureNodeInstalled();
        $this->ensureDependencies($uiDir);

        echo "\033[33m🦊 Fennec UI\033[0m\n";
        echo "\033[33m───────────────────────────────────\033[0m\n";
        echo "  Dashboard : \033[36mhttp://localhost:{$port}\033[0m\n";
        echo "  API proxy : \033[36mhttp://localhost:8080\033[0m\n";
        echo "\033[33m───────────────────────────────────\033[0m\n\n";

        $command = $this->isWindows()
            ? "cd /d \"{$uiDir}\" && npx vite --port {$port}"
            : "cd \"{$uiDir}\" && npx vite --port {$port}";

        passthru($command, $exitCode);

        return $exitCode;
    }

    private function build(string $uiDir): int
    {
        $this->ensureNodeInstalled();
        $this->ensureDependencies($uiDir);

        echo "\033[33m🦊 Building Fennec UI for production...\033[0m\n\n";

        $command = $this->isWindows()
            ? "cd /d \"{$uiDir}\" && npm run build"
            : "cd \"{$uiDir}\" && npm run build";

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            echo "\n\033[32m✓ Build complete → fennec/ui/dist/\033[0m\n";
        }

        return $exitCode;
    }

    private function sync(string $uiDir): int
    {
        $this->ensureNodeInstalled();
        $this->ensureDependencies($uiDir);

        echo "\033[33m🦊 Syncing API types from OpenAPI spec...\033[0m\n\n";

        $command = $this->isWindows()
            ? "cd /d \"{$uiDir}\" && npx orval"
            : "cd \"{$uiDir}\" && npx orval";

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            echo "\n\033[32m✓ API types and hooks regenerated\033[0m\n";
        }

        return $exitCode;
    }

    private function ensureNodeInstalled(): void
    {
        exec('node --version 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            echo "\033[31m✗ Node.js is not installed.\033[0m\n";
            echo "  Install it from: https://nodejs.org\n";
            exit(1);
        }

        $version = trim($output[0] ?? '');
        echo "\033[32m✓\033[0m Node.js {$version}\n";
    }

    private function ensureDependencies(string $uiDir): void
    {
        if (is_dir($uiDir . '/node_modules')) {
            return;
        }

        echo "\033[33m⏳ Installing dependencies...\033[0m\n";

        $command = $this->isWindows()
            ? "cd /d \"{$uiDir}\" && npm install"
            : "cd \"{$uiDir}\" && npm install";

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            echo "\033[31m✗ npm install failed\033[0m\n";
            exit(1);
        }

        echo "\033[32m✓ Dependencies installed\033[0m\n\n";
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
