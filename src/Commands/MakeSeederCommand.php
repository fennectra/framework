<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:seeder', 'Create a new seeder class <name>')]
class MakeSeederCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:seeder <name>\033[0m\n";

            return 1;
        }

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $dir = FENNEC_BASE_PATH . '/database/seeders';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mSeeder {$name} already exists.\033[0m\n";

            return 1;
        }

        $content = <<<PHP
<?php

use Fennec\Core\Migration\Seeder;
use Fennec\Core\DB;

class {$name} extends Seeder
{
    public function run(): void
    {
        \$fake = \$this->fake();

        // DB::raw('INSERT INTO ... VALUES (...)', [...]);
    }
}
PHP;

        file_put_contents($file, $content . "\n");

        echo "\033[32m✓ Seeder created: database/seeders/{$name}.php\033[0m\n";

        return 0;
    }
}
