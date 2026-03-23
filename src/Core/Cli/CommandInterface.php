<?php

namespace Fennec\Core\Cli;

interface CommandInterface
{
    /**
     * Exécute la commande.
     *
     * @param  array $args  Arguments et flags parsés (ex: ['name' => 'User', 'port' => '9000'])
     * @return int           Code de sortie (0 = succès)
     */
    public function execute(array $args): int;
}
