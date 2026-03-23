<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

/**
 * Build, push et deploiement vers Kubernetes.
 *
 * Variables d'environnement :
 *   DEPLOY_REGISTRY   (ex: ghcr.io/org)
 *   DEPLOY_IMAGE      (ex: myapp)
 *   DEPLOY_NAMESPACE  (ex: production)
 */
#[Command('deploy', 'Build, push and deploy to K8s [--tag=latest] [--dry-run]')]
class DeployCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $dryRun = isset($args['dry-run']);
        $tag = $args['tag'] ?? $this->gitShortHash();

        $registry = Env::get('DEPLOY_REGISTRY', '');
        $image = Env::get('DEPLOY_IMAGE', '');
        $namespace = Env::get('DEPLOY_NAMESPACE', 'default');

        if ($registry === '' || $image === '') {
            $this->error('DEPLOY_REGISTRY et DEPLOY_IMAGE doivent etre definis dans .env');

            return 1;
        }

        $fullImage = "{$registry}/{$image}:{$tag}";

        $this->banner($fullImage, $namespace, $dryRun);

        // Etape 1 : Docker Build
        $buildCmd = 'docker build -t ' . escapeshellarg($fullImage) . ' .';
        if ($this->step('Docker Build', $buildCmd, $dryRun) !== 0) {
            return 1;
        }

        // Etape 2 : Docker Push
        $pushCmd = 'docker push ' . escapeshellarg($fullImage);
        if ($this->step('Docker Push', $pushCmd, $dryRun) !== 0) {
            return 1;
        }

        // Etape 3 : Kubectl Set Image
        $setImageCmd = 'kubectl set image deployment/' . escapeshellarg($image)
            . ' ' . escapeshellarg($image) . '=' . escapeshellarg($fullImage)
            . ' -n ' . escapeshellarg($namespace);
        if ($this->step('Kubectl Set Image', $setImageCmd, $dryRun) !== 0) {
            return 1;
        }

        // Etape 4 : Kubectl Rollout Status
        $rolloutCmd = 'kubectl rollout status deployment/' . escapeshellarg($image)
            . ' -n ' . escapeshellarg($namespace)
            . ' --timeout=300s';
        if ($this->step('Kubectl Rollout Status', $rolloutCmd, $dryRun) !== 0) {
            return 1;
        }

        $this->success("Deploiement termine : {$fullImage}");

        return 0;
    }

    /**
     * Execute une etape du deploiement.
     */
    private function step(string $name, string $command, bool $dryRun): int
    {
        echo "\n  \033[1;34m[{$name}]\033[0m\n";
        echo "  \033[90m\$ {$command}\033[0m\n";

        if ($dryRun) {
            echo "  \033[33m(dry-run) commande non executee\033[0m\n";

            return 0;
        }

        $exitCode = 0;
        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $this->error("Etape '{$name}' echouee (code {$exitCode})");
        } else {
            echo "  \033[32m✓ {$name} OK\033[0m\n";
        }

        return $exitCode;
    }

    /**
     * Recupere le hash court du commit courant.
     */
    private function gitShortHash(): string
    {
        $hash = trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null'));

        return $hash !== '' ? $hash : 'latest';
    }

    private function banner(string $image, string $namespace, bool $dryRun): void
    {
        $mode = $dryRun ? ' (DRY RUN)' : '';

        echo "\033[1;35m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   Deploy to Kubernetes{$mode}             \n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";
        echo "  \033[32m➜\033[0m  Image:     \033[1m{$image}\033[0m\n";
        echo "  \033[32m➜\033[0m  Namespace: \033[1m{$namespace}\033[0m\n";
    }

    private function error(string $message): void
    {
        echo "\n  \033[1;31m✗ {$message}\033[0m\n";
    }

    private function success(string $message): void
    {
        echo "\n  \033[1;32m✓ {$message}\033[0m\n\n";
    }
}
