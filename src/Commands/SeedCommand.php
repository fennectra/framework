<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('db:seed', 'Run seeders [--class=DatabaseSeeder]')]
class SeedCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $class = $args['class'] ?? 'DatabaseSeeder';

        $file = FENNEC_BASE_PATH . '/database/seeders/' . $class . '.php';

        if (!file_exists($file)) {
            echo "\033[31mSeeder not found: database/seeders/{$class}.php\033[0m\n";

            return 1;
        }

        require_once $file;

        if (!class_exists($class)) {
            echo "\033[31mClass {$class} not found in {$file}\033[0m\n";

            return 1;
        }

        echo "\033[33mSeeding database...\033[0m\n";

        try {
            $seeder = new $class();
            $seeder->run();
        } catch (\Throwable $e) {
            echo "\033[31mSeeding failed: {$e->getMessage()}\033[0m\n";

            return 1;
        }

        echo "\033[32m✓ Database seeding complete.\033[0m\n";

        return 0;
    }
}
