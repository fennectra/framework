<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Migration\MigrationRunner;

#[Command('migrate', 'Run database migrations [--rollback] [--steps=1] [--status] [--fresh] [--connection=default]')]
class MigrateCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $connection = $args['connection'] ?? 'default';

        try {
            $runner = new MigrationRunner($connection);
        } catch (\Throwable $e) {
            echo "\033[31mDatabase connection error: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        if (isset($args['status'])) {
            return $this->showStatus($runner);
        }

        if (isset($args['fresh'])) {
            return $this->runFresh($runner);
        }

        if (isset($args['rollback'])) {
            $steps = (int) ($args['steps'] ?? 1);

            return $this->runRollback($runner, $steps);
        }

        return $this->runMigrate($runner);
    }

    private function runMigrate(MigrationRunner $runner): int
    {
        echo "\033[33mRunning migrations...\033[0m\n";

        try {
            $migrated = $runner->migrate();
        } catch (\Throwable $e) {
            echo "\033[31mMigration failed: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        if (empty($migrated)) {
            echo "\033[32mNothing to migrate.\033[0m\n";

            return 0;
        }

        foreach ($migrated as $file) {
            echo "  \033[32m✓\033[0m {$file}\n";
        }

        $count = count($migrated);
        echo "\033[32m✓ {$count} migration(s) executed.\033[0m\n";

        return 0;
    }

    private function runRollback(MigrationRunner $runner, int $steps): int
    {
        echo "\033[33mRolling back {$steps} batch(es)...\033[0m\n";

        try {
            $rolledBack = $runner->rollback($steps);
        } catch (\Throwable $e) {
            echo "\033[31mRollback failed: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        if (empty($rolledBack)) {
            echo "\033[32mNothing to rollback.\033[0m\n";

            return 0;
        }

        foreach ($rolledBack as $file) {
            echo "  \033[31m↩\033[0m {$file}\n";
        }

        $count = count($rolledBack);
        echo "\033[32m✓ {$count} migration(s) rolled back.\033[0m\n";

        return 0;
    }

    private function showStatus(MigrationRunner $runner): int
    {
        $status = $runner->status();

        if (empty($status)) {
            echo "\033[33mNo migrations found.\033[0m\n";

            return 0;
        }

        echo "\033[33mMigration status:\033[0m\n";

        foreach ($status as $item) {
            $icon = $item['ran'] ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
            $state = $item['ran'] ? "\033[32mRan\033[0m" : "\033[31mPending\033[0m";
            echo "  {$icon} {$item['migration']} — {$state}\n";
        }

        return 0;
    }

    private function runFresh(MigrationRunner $runner): int
    {
        echo "\033[33mDropping all migrations and re-running...\033[0m\n";

        try {
            $migrated = $runner->fresh();
        } catch (\Throwable $e) {
            echo "\033[31mFresh migration failed: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        foreach ($migrated as $file) {
            echo "  \033[32m✓\033[0m {$file}\n";
        }

        $count = count($migrated);
        echo "\033[32m✓ Fresh migration complete — {$count} migration(s) executed.\033[0m\n";

        return 0;
    }
}
