<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Nf525\ClosingService;

#[Command('nf525:close', 'NF525 period closing [--daily=YYYY-MM-DD] [--monthly=YYYY-MM] [--annual=YYYY]')]
class Nf525CloseCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $service = new ClosingService();

        $daily = $this->parseOption($args, 'daily');
        $monthly = $this->parseOption($args, 'monthly');
        $annual = $this->parseOption($args, 'annual');

        if ($daily === null && $monthly === null && $annual === null) {
            echo "\033[31mUsage: php bin/cli nf525:close [--daily=2026-03-22] [--monthly=2026-03] [--annual=2026]\033[0m\n";

            return 1;
        }

        try {
            if ($daily !== null) {
                $result = $service->closeDaily($daily);
                $this->printResult('Journaliere', $result);
            }

            if ($monthly !== null) {
                $result = $service->closeMonthly($monthly);
                $this->printResult('Mensuelle', $result);
            }

            if ($annual !== null) {
                $result = $service->closeAnnual($annual);
                $this->printResult('Annuelle', $result);
            }
        } catch (\RuntimeException $e) {
            echo "\033[31m✗ {$e->getMessage()}\033[0m\n";

            return 1;
        }

        return 0;
    }

    private function printResult(string $type, array $result): void
    {
        echo "\033[32m✓ Cloture {$type}\033[0m\n";
        echo "  Periode: {$result['period_start']} → {$result['period_end']}\n";
        echo "  Documents: {$result['totals']['document_count']}\n";
        echo "  Total HT: {$result['totals']['total_ht']} EUR\n";
        echo "  TVA: {$result['totals']['total_tva']} EUR\n";
        echo "  Total TTC: {$result['totals']['total_ttc']} EUR\n";
        echo "  Cumul: {$result['cumulative_total']} EUR\n";
        echo "  Hash: {$result['hash']}\n\n";
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
