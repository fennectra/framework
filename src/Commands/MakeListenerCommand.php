<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:listener', 'Create an event listener [--event=UserCreated]')]
class MakeListenerCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:listener <Nom> [--event=EventClass]\033[0m\n";
            echo "  Exemple: php bin/cli make:listener SendWelcomeEmail --event=UserCreated\n";

            return 1;
        }

        $eventName = $args['event'] ?? 'SomeEvent';

        $dir = FENNEC_BASE_PATH . '/app/Listeners';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mLe listener {$name} existe déjà.\033[0m\n";

            return 1;
        }

        $eventFqcn = "App\\Events\\{$eventName}";

        $content = <<<PHP
<?php

namespace App\\Listeners;

use Fennec\\Attributes\\Listener;
use {$eventFqcn};

#[Listener({$eventName}::class)]
class {$name}
{
    public function handle({$eventName} \$event): void
    {
        // TODO: implémenter la logique
    }
}

PHP;

        file_put_contents($file, $content);

        echo "\033[32m✓ Listener créé : app/Listeners/{$name}.php\033[0m\n";
        echo "  Event : {$eventFqcn}\n";
        echo "\n  \033[33mLe listener sera auto-découvert au démarrage de l'app.\033[0m\n";

        return 0;
    }
}
