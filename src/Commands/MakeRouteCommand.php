<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:route', 'Create a new route file')]
class MakeRouteCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:route <Nom> [--prefix=/api] [--middleware=auth] [--roles=admin,manager]\033[0m\n";

            return 1;
        }

        $fileName = strtolower($name);
        $prefix = $args['prefix'] ?? '/' . $fileName;
        $middleware = $args['middleware'] ?? null;
        $roles = $args['roles'] ?? null;
        $description = ucfirst($name) . ' routes';

        $dir = FENNEC_BASE_PATH . '/app/Routes';
        $file = "{$dir}/{$fileName}.php";

        if (file_exists($file)) {
            echo "\033[31mLe fichier de routes {$fileName}.php existe déjà.\033[0m\n";

            return 1;
        }

        $lines = ["<?php\n"];

        // Construire le middleware
        if ($middleware === 'auth' && $roles) {
            $rolesArray = implode("', '", explode(',', $roles));
            $lines[] = "use App\\Middleware\\Auth;\n";
            $middlewareLine = "    'middleware' => [[Auth::class, ['{$rolesArray}']]],";
        } elseif ($middleware === 'auth') {
            $lines[] = "use App\\Middleware\\Auth;\n";
            $middlewareLine = "    'middleware' => [[Auth::class, null]],";
        } else {
            $middlewareLine = null;
        }

        $lines[] = '';
        $lines[] = '$router->group([';
        $lines[] = "    'prefix' => '{$prefix}',";
        $lines[] = "    'description' => '{$description}',";
        if ($middlewareLine) {
            $lines[] = $middlewareLine;
        }
        $lines[] = '], function ($router) {';
        $lines[] = '    // Définir les routes ici';
        $lines[] = "    // \$router->get('', [Controller::class, 'index']);";
        $lines[] = '});';
        $lines[] = '';

        file_put_contents($file, implode("\n", $lines));

        echo "\033[32m✓ Routes créées : app/Routes/{$fileName}.php\033[0m\n";
        echo "  Prefix : {$prefix}\n";
        if ($roles) {
            echo "  Rôles : {$roles}\n";
        }

        return 0;
    }
}
