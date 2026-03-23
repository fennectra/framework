<?php

namespace Fennec\Core\Cli;

use Fennec\Attributes\Command;
use Fennec\Core\Container;

class CommandRunner
{
    /** @var array<string, array{class: string, description: string}> */
    private array $commands = [];

    /** @var array<string, string> Group labels */
    private static array $groups = [
        'make'     => 'Scaffolding',
        'migrate'  => 'Database',
        'db'       => 'Database',
        'tinker'   => 'Database',
        'serve'    => 'Server',
        'deploy'   => 'Server',
        'queue'    => 'Queue & Jobs',
        'schedule' => 'Queue & Jobs',
        'cache'    => 'Tools',
        'quality'  => 'Tools',
        'storage'  => 'Tools',
        'tutorials' => 'Tools',
        'feature'  => 'Features',
        'nf525'    => 'Compliance',
        'audit'    => 'Compliance',
        'ui'       => 'Tools',
    ];

    public function __construct(
        private Container $container,
    ) {
    }

    /**
     * Discover commands in a directory (scans PHP files with #[Command] attribute).
     */
    public function discoverCommands(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $className = $this->resolveClassName($file);
            if ($className === null || !class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);
            $attrs = $ref->getAttributes(Command::class);

            if (empty($attrs)) {
                continue;
            }

            $command = $attrs[0]->newInstance();
            $this->commands[$command->name] = [
                'class' => $className,
                'description' => $command->description,
            ];
        }
    }

    /**
     * Execute a command from CLI arguments.
     */
    public function run(array $argv): int
    {
        array_shift($argv);

        if (empty($argv)) {
            $this->showGroupedHelp();

            return 0;
        }

        $commandName = array_shift($argv);

        if ($commandName === 'help' || $commandName === '--help') {
            $this->showGroupedHelp();

            return 0;
        }

        // Check if it's a group name (e.g., "./forge make" to list make:* commands)
        if (!isset($this->commands[$commandName]) && !str_contains($commandName, ':')) {
            $groupCommands = $this->getCommandsByPrefix($commandName);
            if (!empty($groupCommands)) {
                $this->showGroupCommands($commandName, $groupCommands);

                return 0;
            }
        }

        if (!isset($this->commands[$commandName])) {
            echo "\033[31mUnknown command: {$commandName}\033[0m\n\n";
            $this->showGroupedHelp();

            return 1;
        }

        $args = $this->parseArgs($argv);
        $command = $this->container->get($this->commands[$commandName]['class']);

        if (!$command instanceof CommandInterface) {
            echo "\033[31mCommand {$commandName} must implement CommandInterface\033[0m\n";

            return 1;
        }

        return $command->execute($args);
    }

    /**
     * Parse CLI arguments: --port=9000 → ['port' => '9000'], arg → ['0' => 'arg']
     */
    private function parseArgs(array $argv): array
    {
        $args = [];
        $positional = 0;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $args[$key] = $value;
                } else {
                    $args[$arg] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $args[substr($arg, 1)] = true;
            } else {
                $args[$positional++] = $arg;
            }
        }

        return $args;
    }

    /**
     * Show help grouped by theme.
     */
    private function showGroupedHelp(): void
    {
        $this->printBanner();

        echo "\033[33mUsage:\033[0m\n";
        echo "  ./forge <command> [options]\n\n";

        // Group commands
        $grouped = [];
        foreach ($this->commands as $name => $info) {
            $group = $this->resolveGroup($name);
            $grouped[$group][$name] = $info['description'];
        }

        // Display order
        $order = ['Scaffolding', 'Database', 'Server', 'Queue & Jobs', 'Features', 'Compliance', 'Tools'];

        foreach ($order as $groupName) {
            if (!isset($grouped[$groupName])) {
                continue;
            }
            echo "  \033[1;33m{$groupName}\033[0m\n";
            ksort($grouped[$groupName]);
            foreach ($grouped[$groupName] as $cmd => $desc) {
                echo "    \033[32m{$cmd}\033[0m  {$desc}\n";
            }
            echo "\n";
        }

        // Any ungrouped commands
        if (isset($grouped['Other'])) {
            echo "  \033[1;33mOther\033[0m\n";
            foreach ($grouped['Other'] as $cmd => $desc) {
                echo "    \033[32m{$cmd}\033[0m  {$desc}\n";
            }
            echo "\n";
        }

        echo "\033[90mRun ./forge <group> for details. Example: ./forge make\033[0m\n\n";
    }

    /**
     * Show commands for a specific prefix group (e.g., "make").
     */
    private function showGroupCommands(string $prefix, array $commands): void
    {
        $this->printBanner();

        $groupLabel = self::$groups[$prefix] ?? ucfirst($prefix);
        echo "\033[33m{$groupLabel} commands:\033[0m\n\n";

        $maxLen = 0;
        foreach ($commands as $name => $desc) {
            $maxLen = max($maxLen, strlen($name));
        }

        ksort($commands);
        foreach ($commands as $name => $desc) {
            $padding = str_repeat(' ', $maxLen - strlen($name) + 2);
            echo "  \033[32m{$name}\033[0m{$padding}{$desc}\n";
        }
        echo "\n";
    }

    /**
     * Get all commands matching a prefix (e.g., "make" → make:all, make:controller, ...)
     */
    private function getCommandsByPrefix(string $prefix): array
    {
        $result = [];
        foreach ($this->commands as $name => $info) {
            if (str_starts_with($name, $prefix . ':') || $name === $prefix) {
                $result[$name] = $info['description'];
            }
        }

        return $result;
    }

    /**
     * Resolve which group a command belongs to.
     */
    private function resolveGroup(string $commandName): string
    {
        // Check exact match first (e.g., "serve", "tinker")
        if (isset(self::$groups[$commandName])) {
            return self::$groups[$commandName];
        }

        // Check prefix (e.g., "make:controller" → "make")
        $prefix = explode(':', $commandName)[0];
        if (isset(self::$groups[$prefix])) {
            return self::$groups[$prefix];
        }

        return 'Other';
    }

    private function printBanner(): void
    {
        echo "\033[1;36m  ___ ___ _  _ _  _ ___ ___  \033[0m\n";
        echo "\033[1;36m | __| __| \\| | \\| | __/ __| \033[0m\n";
        echo "\033[1;36m | _|| _|| .` | .` | _| (__  \033[0m\n";
        echo "\033[1;36m |_| |___|_|\\_|_|\\_|___\\___| \033[0m\n";
        echo "\n";
        echo "\033[1mFennectra CLI\033[0m\n\n";
    }

    /**
     * Resolve fully qualified class name from a PHP file.
     */
    private function resolveClassName(string $file): ?string
    {
        $content = file_get_contents($file);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+(.+?);/', $content, $m)) {
            $namespace = $m[1];
        }
        if (preg_match('/class\s+(\w+)/', $content, $m)) {
            $class = $m[1];
        }

        if (empty($class)) {
            return null;
        }

        return $namespace ? "{$namespace}\\{$class}" : $class;
    }
}
