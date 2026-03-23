<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\DB;

#[Command('tinker', 'Execute SQL query and display results')]
class TinkerCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $sql = $this->getOption($args, 'sql');
        $connection = $this->getOption($args, 'connection', 'default');

        if (!$sql) {
            echo "\033[31mUsage: ./forge tinker --sql=\"SELECT ...\" [--connection=default]\033[0m\n";
            echo "\n";
            echo "  --sql         Requete SQL a executer\n";
            echo "  --connection  Connexion DB (default, job, test)\n";
            echo "\n";
            echo "Exemples:\n";
            echo "  ./forge tinker --sql=\"SELECT table_name FROM information_schema.tables WHERE table_schema='public'\"\n";
            echo "  ./forge tinker --sql=\"SELECT * FROM users LIMIT 5\"\n";
            echo "  ./forge tinker --sql=\"\\dt\" \033[90m(raccourci pour lister les tables)\033[0m\n";

            return 1;
        }

        // Raccourcis pratiques
        $shortcuts = [
            '\dt' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
            '\d' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
            '\du' => 'SELECT usename FROM pg_user ORDER BY usename',
        ];

        // Raccourci \d <table> — describe table
        if (preg_match('/^\\\\d\s+(\w+)$/', $sql, $m)) {
            $sql = "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '{$m[1]}' ORDER BY ordinal_position";
        } elseif (isset($shortcuts[$sql])) {
            $sql = $shortcuts[$sql];
        }

        try {
            $stmt = DB::raw($sql, [], $connection);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo "\033[33m(0 rows)\033[0m\n";

                return 0;
            }

            // Calcul des largeurs de colonnes
            $headers = array_keys($rows[0]);
            $widths = [];
            foreach ($headers as $h) {
                $widths[$h] = mb_strlen($h);
            }
            foreach ($rows as $row) {
                foreach ($headers as $h) {
                    $len = mb_strlen((string) ($row[$h] ?? 'NULL'));
                    if ($len > $widths[$h]) {
                        $widths[$h] = min($len, 60);
                    }
                }
            }

            // Header
            $line = '';
            foreach ($headers as $h) {
                $line .= ' ' . str_pad($h, $widths[$h]) . ' |';
            }
            echo "\033[1m" . rtrim($line, '|') . "\033[0m\n";
            $sep = '';
            foreach ($headers as $h) {
                $sep .= '-' . str_repeat('-', $widths[$h]) . '-+';
            }
            echo rtrim($sep, '+') . "\n";

            // Rows
            foreach ($rows as $row) {
                $line = '';
                foreach ($headers as $h) {
                    $val = $row[$h] ?? 'NULL';
                    $display = mb_substr((string) $val, 0, 60);
                    $line .= ' ' . str_pad($display, $widths[$h]) . ' |';
                }
                echo rtrim($line, '|') . "\n";
            }

            echo "\033[32m(" . count($rows) . " rows)\033[0m\n";

            return 0;
        } catch (\Exception $e) {
            echo "\033[31mErreur: " . $e->getMessage() . "\033[0m\n";

            return 1;
        }
    }

    private function getOption(array $args, string $name, string $default = ''): string
    {
        return (string) ($args[$name] ?? $default);
    }
}
