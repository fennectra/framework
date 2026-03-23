<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:event', 'Create an event class')]
class MakeEventCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:event <Nom>\033[0m\n";
            echo "  Exemple: php bin/cli make:event UserCreated\n";

            return 1;
        }

        $dir = FENNEC_BASE_PATH . '/app/Events';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mL'event {$name} existe déjà.\033[0m\n";

            return 1;
        }

        $content = <<<PHP
<?php

namespace App\\Events;

readonly class {$name}
{
    public function __construct(
        public mixed \$payload,
    ) {
    }
}

PHP;

        file_put_contents($file, $content);

        echo "\033[32m✓ Event créé : app/Events/{$name}.php\033[0m\n";
        echo "\n  \033[33mUsage :\033[0m\n";
        echo "    use App\\Events\\{$name};\n";
        echo "    use Fennec\\Core\\Event;\n";
        echo "\n";
        echo "    Event::dispatch(new {$name}(\$data));\n";

        return 0;
    }
}
