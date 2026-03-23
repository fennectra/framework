<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('serve', 'Start the server [--frankenphp] [--worker] [--port=8080]')]
class ServeCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $port = $args['port'] ?? '8080';
        $host = $args['host'] ?? '0.0.0.0';
        $frankenphp = isset($args['frankenphp']);
        $worker = isset($args['worker']);
        $projectRoot = FENNEC_BASE_PATH;
        $docRoot = $projectRoot . '/public';

        if ($frankenphp) {
            return $this->serveFrankenPhp($host, $port, $projectRoot, $worker);
        }

        return $this->serveBuiltIn($host, $port, $docRoot);
    }

    private function serveBuiltIn(string $host, string $port, string $docRoot): int
    {
        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   PHP Built-in Dev Server            ║\n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";
        echo "  \033[32m➜\033[0m  Local:   \033[1mhttp://localhost:{$port}\033[0m\n";
        echo "  \033[32m➜\033[0m  Network: \033[1mhttp://{$host}:{$port}\033[0m\n";
        echo "  \033[33m➜\033[0m  Root:    {$docRoot}\n";
        echo "\n  \033[90mAppuyez sur Ctrl+C pour arrêter\033[0m\n\n";

        $exitCode = 0;
        $router = $docRoot . '/router.php';
        passthru(escapeshellarg(PHP_BINARY) . " -S {$host}:{$port} -t " . escapeshellarg($docRoot) . ' ' . escapeshellarg($router), $exitCode);

        return $exitCode;
    }

    private function serveFrankenPhp(string $host, string $port, string $projectRoot, bool $worker): int
    {
        $mode = $worker ? 'Worker' : 'Classique';

        // 1. FrankenPHP natif dans le PATH ?
        $frankenBin = $this->findBinary('frankenphp');
        if ($frankenBin) {
            return $this->runNativeFrankenPhp($frankenBin, $host, $port, $projectRoot, $worker, $mode);
        }

        // 2. Docker disponible ?
        $dockerBin = $this->findBinary('docker');
        if (!$dockerBin) {
            echo "\033[31m✗ Ni FrankenPHP ni Docker ne sont installés\033[0m\n\n";
            echo "  Installez Docker : https://docs.docker.com/get-docker/\n\n";

            return 1;
        }

        // 3. Lancer via Docker automatiquement
        return $this->runDockerFrankenPhp($dockerBin, $host, $port, $projectRoot, $worker, $mode);
    }

    private function runNativeFrankenPhp(string $bin, string $host, string $port, string $projectRoot, bool $worker, string $mode): int
    {
        $docRoot = $projectRoot . '/public';
        $bin = escapeshellarg($bin);

        $this->printBanner('FrankenPHP', $mode, $port, $host, $docRoot, $worker);

        $exitCode = 0;
        $cmd = "{$bin} php-server --listen " . escapeshellarg("{$host}:{$port}")
            . ' --root ' . escapeshellarg($docRoot);

        if ($worker) {
            $cmd .= ' --worker ' . escapeshellarg($docRoot . '/worker.php');
        }

        passthru($cmd, $exitCode);

        return $exitCode;
    }

    private function runDockerFrankenPhp(string $dockerBin, string $host, string $port, string $projectRoot, bool $worker, string $mode): int
    {
        $containerName = 'frankenphp-dev';
        $imageName = 'frankenphp-dev';
        $docker = escapeshellarg($dockerBin);
        $dockerfilePath = dirname(__DIR__, 2) . '/docker/Dockerfile.frankenphp';

        // Builder l'image custom (avec pdo_pgsql, pdo_mysql, composer)
        $imageCheck = shell_exec("{$docker} images -q {$imageName} 2>&1");
        if (empty(trim((string) $imageCheck)) || isset($this->args['build'])) {
            echo "  \033[33m⏳ Build de l'image FrankenPHP...\033[0m\n";
            $buildResult = 0;
            passthru(
                "{$docker} build -f " . escapeshellarg($dockerfilePath)
                . ' -t ' . $imageName
                . ' ' . escapeshellarg($projectRoot),
                $buildResult
            );
            if ($buildResult !== 0) {
                echo "\033[31m✗ Échec du build Docker\033[0m\n";

                return 1;
            }
            echo "\n";
        }

        // Stopper un éventuel ancien container
        shell_exec("{$docker} rm -f {$containerName} 2>&1");

        $this->printBanner('FrankenPHP (Docker)', $mode, $port, $host, '/app/public', $worker);
        echo "  \033[33m➜\033[0m  Container: {$containerName}\n";

        // Lire le .env et remplacer localhost par host.docker.internal
        $envVars = $this->loadEnvForDocker($projectRoot . '/.env');
        echo "  \033[33m➜\033[0m  DB Host:   " . ($envVars['POSTGRES_HOST'] ?? '?') . "\n";
        echo "\n";

        // Convertir le chemin Windows en format Docker
        $mountPath = str_replace('\\', '/', $projectRoot);

        // Construire la commande Docker
        $dockerCmd = "{$docker} run --rm --name " . $containerName
            . ' --add-host=host.docker.internal:host-gateway'
            . ' -p ' . escapeshellarg("{$port}:{$port}")
            . ' -v ' . escapeshellarg("{$mountPath}:/app")
            . ' -w /app';

        // Passer chaque env var individuellement (avec localhost -> host.docker.internal)
        foreach ($envVars as $key => $value) {
            $dockerCmd .= ' -e ' . escapeshellarg("{$key}={$value}");
        }

        $dockerCmd .= ' ' . $imageName;

        if ($worker) {
            // Mode worker : Caddyfile.dev configure le worker dans le bloc frankenphp{}
            // frankenphp run (pas php-server) pour que les requetes passent par le worker
            $dockerCmd .= ' -e PORT=' . $port;
            $dockerCmd .= ' frankenphp run --config /app/vendor/fennectra/framework/docker/Caddyfile.dev';
        } else {
            $dockerCmd .= ' frankenphp php-server'
                . ' --listen 0.0.0.0:' . $port
                . ' --root /app/public';
        }

        $exitCode = 0;
        passthru($dockerCmd, $exitCode);

        return $exitCode;
    }

    /**
     * Charge le .env et remplace localhost/127.0.0.1 par host.docker.internal.
     *
     * @return array<string, string>
     */
    private function loadEnvForDocker(string $envFile): array
    {
        $vars = [];
        if (!file_exists($envFile)) {
            return $vars;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');

            // Remplacer localhost par host.docker.internal pour les hosts DB
            if (str_contains($key, '_HOST') && in_array($value, ['localhost', '127.0.0.1'])) {
                $value = 'host.docker.internal';
            }

            $vars[$key] = $value;
        }

        return $vars;
    }

    private function printBanner(string $engine, string $mode, string $port, string $host, string $docRoot, bool $worker): void
    {
        echo "\033[1;35m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   {$engine}                          \n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";
        echo "  \033[32m➜\033[0m  Local:   \033[1mhttp://localhost:{$port}\033[0m\n";
        echo "  \033[32m➜\033[0m  Mode:    \033[1m{$mode}\033[0m\n";
        echo "  \033[33m➜\033[0m  Root:    {$docRoot}\n";

        if ($worker) {
            echo "  \033[33m➜\033[0m  Worker:  public/worker.php\n";
            echo "\n  \033[90mL'app reste en mémoire — performances maximales\033[0m\n";
        }

        echo "\n  \033[90mAppuyez sur Ctrl+C pour arrêter\033[0m\n";
    }

    /**
     * Cherche un binaire et retourne son chemin absolu, ou null.
     */
    private function findBinary(string $name): ?string
    {
        // Windows CMD : where
        exec("where {$name} 2>&1", $out1, $code1);
        if ($code1 === 0 && !empty($out1[0])) {
            return trim($out1[0]);
        }

        // Bash / Git Bash : which
        exec("which {$name} 2>&1", $out2, $code2);
        if ($code2 === 0 && !empty($out2[0])) {
            $path = trim($out2[0]);
            // Convertir /c/Program Files/... en C:\Program Files\...
            if (preg_match('#^/([a-zA-Z])/#', $path, $m)) {
                $path = strtoupper($m[1]) . ':' . str_replace('/', '\\', substr($path, 2));
            }

            return $path;
        }

        return null;
    }
}
