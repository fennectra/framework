<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('tutorials', 'Serve tutorials site [--port=3002] [--open]')]
class TutorialsCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $port = (int) ($args['port'] ?? 3002);
        $docRoot = dirname(__DIR__, 2) . '/docs/tutorials';

        if (!is_dir($docRoot)) {
            echo "\033[31m  Tutorials directory not found: {$docRoot}\033[0m\n";

            return 1;
        }

        echo "\n\033[1;36m  Fennec Tutorials\033[0m\n";
        echo "  \033[32mhttp://localhost:{$port}\033[0m\n";
        echo "  \033[90mPress Ctrl+C to stop\033[0m\n\n";

        // Use PHP built-in server
        $command = escapeshellarg(PHP_BINARY) . " -S localhost:{$port} -t " . escapeshellarg($docRoot);

        $exitCode = 0;
        passthru($command, $exitCode);

        return $exitCode;
    }
}
