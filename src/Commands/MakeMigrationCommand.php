<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:migration', 'Create a new migration <name>')]
class MakeMigrationCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:migration <name>\033[0m\n";

            return 1;
        }

        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}";

        $dir = FENNEC_BASE_PATH . '/database/migrations';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$filename}.php";

        $content = <<<'PHP'
<?php

return [
    'up' => '-- SQL here',
    'down' => '-- SQL here',
];
PHP;

        file_put_contents($file, $content . "\n");

        echo "\033[32m✓ Migration created: database/migrations/{$filename}.php\033[0m\n";

        return 0;
    }
}
