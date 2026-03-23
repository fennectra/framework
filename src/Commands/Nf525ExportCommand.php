<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Nf525\FecExporter;

#[Command('nf525:export', 'Export FEC file [--year=YYYY] [--output=path]')]
class Nf525ExportCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $year = $this->parseOption($args, 'year') ?? date('Y');
        $output = $this->parseOption($args, 'output');

        $exporter = new FecExporter();
        $count = $exporter->count($year);

        if ($count === 0) {
            echo "\033[33m⚠ Aucune ecriture trouvee pour {$year}\033[0m\n";

            return 0;
        }

        $path = $exporter->exportToFile($year, $output);

        echo "\033[32m✓ Export FEC genere\033[0m\n";
        echo "  Annee: {$year}\n";
        echo "  Ecritures: {$count}\n";
        echo "  Fichier: {$path}\n";

        return 0;
    }

    private function parseOption(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
        }

        return null;
    }
}
