<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\DB;
use Fennec\Core\Env;

#[Command('audit:purge', 'Purge old audit logs (ISO 27001 A.5.33 data retention)')]
class AuditPurgeCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $retentionDays = $this->parseOption($args, 'days')
            ?? Env::get('AUDIT_RETENTION_DAYS', '365');
        $retentionDays = (int) $retentionDays;

        $dryRun = in_array('--dry-run', $args, true);

        echo "\033[1mAudit Log Purge (retention: {$retentionDays} days)\033[0m\n\n";

        $tables = [
            'audit_logs' => 'created_at',
        ];

        $totalPurged = 0;

        foreach ($tables as $table => $column) {
            $count = $this->countOldRecords($table, $column, $retentionDays);

            if ($count === 0) {
                echo "  {$table}: \033[32m0 records to purge\033[0m\n";
                continue;
            }

            if ($dryRun) {
                echo "  {$table}: \033[33m{$count} records would be purged\033[0m (dry-run)\n";
            } else {
                $purged = $this->purge($table, $column, $retentionDays);
                echo "  {$table}: \033[32m{$purged} records purged\033[0m\n";
                $totalPurged += $purged;
            }
        }

        echo "\n";

        if ($dryRun) {
            echo "\033[33mDry-run mode — no records deleted. Remove --dry-run to execute.\033[0m\n";
        } else {
            echo "\033[32m✓ Purge complete: {$totalPurged} records deleted\033[0m\n";
        }

        return 0;
    }

    private function countOldRecords(string $table, string $column, int $days): int
    {
        try {
            $driver = Env::get('DB_DRIVER', 'pgsql');
            $interval = $this->buildInterval($driver, $days);

            $stmt = DB::raw(
                "SELECT COUNT(*) FROM {$table} WHERE {$column} < {$interval}",
            );

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            echo "  \033[31m{$table}: table not found (run make:audit first)\033[0m\n";

            return 0;
        }
    }

    private function purge(string $table, string $column, int $days): int
    {
        $driver = Env::get('DB_DRIVER', 'pgsql');
        $interval = $this->buildInterval($driver, $days);

        $stmt = DB::raw(
            "DELETE FROM {$table} WHERE {$column} < {$interval}",
        );

        return $stmt->rowCount();
    }

    private function buildInterval(string $driver, int $days): string
    {
        return match ($driver) {
            'mysql' => "DATE_SUB(NOW(), INTERVAL {$days} DAY)",
            'sqlite' => "datetime('now', '-{$days} days')",
            default => "NOW() - INTERVAL '{$days} days'",
        };
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
