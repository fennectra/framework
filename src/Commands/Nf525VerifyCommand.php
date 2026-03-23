<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Nf525\HashChainVerifier;

#[Command('nf525:verify', 'Verify NF525 hash chain integrity [--table=invoices]')]
class Nf525VerifyCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $table = $this->parseOption($args, 'table') ?? 'invoices';

        echo "\033[1mNF525 Hash Chain Verification — {$table}\033[0m\n\n";

        try {
            $result = HashChainVerifier::verify($table);
        } catch (\Throwable $e) {
            echo "\033[31m✗ Erreur: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        echo "  Documents verifies: {$result['total']}\n";

        if ($result['valid']) {
            echo "\n\033[32m✓ Chaine de hash integre — aucune anomalie detectee\033[0m\n";

            return 0;
        }

        echo "\n\033[31m✗ " . count($result['errors']) . " anomalie(s) detectee(s) :\033[0m\n\n";

        foreach ($result['errors'] as $error) {
            echo "  ID #{$error['id']}: {$error['error']}\n";

            if (isset($error['expected'])) {
                echo "    Attendu: {$error['expected']}\n";
                echo "    Trouve:  {$error['actual']}\n";
            }

            if (isset($error['expected_prev'])) {
                echo "    Previous attendu: {$error['expected_prev']}\n";
                echo "    Previous trouve:  {$error['actual_prev']}\n";
            }
        }

        return 1;
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
