<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\DB;
use Fennec\Core\Feature\FeatureFlag;

#[Command('feature', 'Manage feature flags [enable|disable|list|delete] <name>')]
class FeatureFlagCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $action = $args[0] ?? 'list';
        $name = $args[1] ?? null;

        return match ($action) {
            'enable' => $this->enable($name),
            'disable' => $this->disable($name),
            'list' => $this->list(),
            'delete' => $this->delete($name),
            default => $this->usage(),
        };
    }

    private function enable(?string $name): int
    {
        if (!$name) {
            echo "\033[31mUsage: php bin/cli feature enable <name>\033[0m\n";

            return 1;
        }

        FeatureFlag::activate($name);
        echo "\033[32m✓ Feature '{$name}' activee.\033[0m\n";

        return 0;
    }

    private function disable(?string $name): int
    {
        if (!$name) {
            echo "\033[31mUsage: php bin/cli feature disable <name>\033[0m\n";

            return 1;
        }

        FeatureFlag::deactivate($name);
        echo "\033[32m✓ Feature '{$name}' desactivee.\033[0m\n";

        return 0;
    }

    private function list(): int
    {
        try {
            $rows = DB::table('feature_flags')->orderBy('key')->get();
        } catch (\Throwable $e) {
            echo "\033[31m✗ Erreur : {$e->getMessage()}\033[0m\n";
            echo "  Assurez-vous que la table feature_flags existe.\n";

            return 1;
        }

        if (empty($rows)) {
            echo "\033[33mAucun feature flag defini.\033[0m\n";

            return 0;
        }

        echo "\033[1mFeature Flags :\033[0m\n\n";
        echo str_pad('Nom', 30) . str_pad('Statut', 12) . "Regles\n";
        echo str_repeat('─', 70) . "\n";

        foreach ($rows as $row) {
            $status = ((bool) $row['enabled'])
                ? "\033[32mACTIF\033[0m  "
                : "\033[31mINACTIF\033[0m";
            $rules = $row['rules'] ?? '-';

            echo str_pad($row['key'], 30) . str_pad($status, 20) . $rules . "\n";
        }

        return 0;
    }

    private function delete(?string $name): int
    {
        if (!$name) {
            echo "\033[31mUsage: php bin/cli feature delete <name>\033[0m\n";

            return 1;
        }

        try {
            $deleted = DB::table('feature_flags')->where('key', $name)->delete();
        } catch (\Throwable $e) {
            echo "\033[31m✗ Erreur : {$e->getMessage()}\033[0m\n";

            return 1;
        }

        if ($deleted > 0) {
            echo "\033[32m✓ Feature '{$name}' supprimee.\033[0m\n";
        } else {
            echo "\033[33m⚠ Feature '{$name}' introuvable.\033[0m\n";
        }

        return 0;
    }

    private function usage(): int
    {
        echo "\033[1mUsage :\033[0m\n\n";
        echo "  php bin/cli feature list              Lister les feature flags\n";
        echo "  php bin/cli feature enable <name>     Activer un flag\n";
        echo "  php bin/cli feature disable <name>    Desactiver un flag\n";
        echo "  php bin/cli feature delete <name>     Supprimer un flag\n";

        return 0;
    }
}
