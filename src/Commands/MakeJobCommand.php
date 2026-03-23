<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:job', 'Create a new Job class <name>')]
class MakeJobCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:job <Nom>\033[0m\n";

            return 1;
        }

        $dir = FENNEC_BASE_PATH . '/app/Jobs';
        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mLe job {$name} existe deja.\033[0m\n";

            return 1;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = <<<PHP
<?php

namespace App\\Jobs;

use Fennec\\Core\\Queue\\JobInterface;

class {$name} implements JobInterface
{
    public function handle(array \$payload): void
    {
        // TODO: implementer la logique du job
    }

    public function retries(): int
    {
        return 3;
    }

    public function retryDelay(): int
    {
        return 60;
    }

    public function failed(array \$payload, \\Throwable \$e): void
    {
        // Logique en cas d'echec definitif (notification, log, etc.)
    }
}

PHP;

        file_put_contents($file, $content);

        echo "\033[32m✓ Job cree : app/Jobs/{$name}.php\033[0m\n";
        echo "\n  \033[33mUsage :\033[0m\n";
        echo "    Job::dispatch(\\App\\Jobs\\{$name}::class, ['key' => 'value']);\n";
        echo "    Job::dispatch(\\App\\Jobs\\{$name}::class, [], 'high-priority');\n";

        return 0;
    }
}
