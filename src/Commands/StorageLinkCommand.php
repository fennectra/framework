<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('storage:link', 'Create symlink public/storage → storage/')]
class StorageLinkCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $projectRoot = FENNEC_BASE_PATH;
        $target = $projectRoot . '/storage';
        $link = $projectRoot . '/public/storage';

        if (!is_dir($target)) {
            mkdir($target, 0775, true);
            echo "  \033[32m✓\033[0m Dossier storage/ créé\n";
        }

        if (file_exists($link) || is_link($link)) {
            echo "  \033[33m⚠\033[0m Le lien public/storage existe déjà\n";

            return 0;
        }

        // Sous Windows, symlink nécessite les droits admin
        if (PHP_OS_FAMILY === 'Windows') {
            exec('mklink /D ' . escapeshellarg($link) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $code);
        } else {
            $code = symlink($target, $link) ? 0 : 1;
        }

        if ($code === 0) {
            echo "  \033[32m✓\033[0m Lien symbolique créé : public/storage → storage/\n";

            return 0;
        }

        echo "  \033[31m✗\033[0m Impossible de créer le lien symbolique\n";
        if (PHP_OS_FAMILY === 'Windows') {
            echo "    Essaye en administrateur ou active le mode développeur Windows\n";
        }

        return 1;
    }
}
