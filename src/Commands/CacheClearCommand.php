<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cache\FileCache;
use Fennec\Core\Cli\CommandInterface;

#[Command('cache:clear', 'Clear all caches')]
class CacheClearCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $cache = new FileCache();
        $cache->clear();

        echo "\033[32m✓ Cache vidé avec succès\033[0m\n";

        return 0;
    }
}
