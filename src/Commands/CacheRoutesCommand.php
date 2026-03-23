<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cache\FileCache;
use Fennec\Core\Cache\RouteCache;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Router;

#[Command('cache:routes', 'Pre-compile route cache')]
class CacheRoutesCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        // Charger les routes comme le ferait index.php
        $router = new Router();
        require FENNEC_BASE_PATH . '/app/routes.php';

        $routeCache = new RouteCache(new FileCache());
        $routeCache->compile($router->getRoutes());

        $count = count($router->getRoutes());
        echo "\033[32m✓ {$count} routes compilées et mises en cache\033[0m\n";

        return 0;
    }
}
