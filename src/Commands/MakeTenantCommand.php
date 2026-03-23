<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:tenant', 'Generate multi-tenancy config file')]
class MakeTenantCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $configDir = FENNEC_BASE_PATH . '/app/config';
        $configFile = $configDir . '/tenants.php';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Multi-Tenancy — Configuration              ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        if (file_exists($configFile)) {
            echo "  \033[33m⚠ Le fichier app/config/tenants.php existe deja\033[0m\n\n";

            return 0;
        }

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $content = <<<'PHP'
<?php

/**
 * Configuration multi-tenancy.
 *
 * Chaque tenant est identifie par un ID unique et relie a une base de donnees
 * distincte. La resolution se fait par domaine ou par port.
 *
 * Les credentials DB sont lues depuis les variables d'environnement.
 * Convention : POSTGRES_TENANT_{ID}_HOST, POSTGRES_TENANT_{ID}_DB, etc.
 */

return [

    // ── Mapping domaine → tenant ────────────────────────────────
    // Supporte les wildcards : '*.client1.com' matche tout sous-domaine
    'domains' => [
        // 'client1.example.com' => 'client1',
        // 'client2.example.com' => 'client2',
        // '*.client3.com'       => 'client3',
    ],

    // ── Mapping port → tenant ───────────────────────────────────
    // Utile en dev local pour tester plusieurs tenants
    'ports' => [
        // 8081 => 'client1',
        // 8082 => 'client2',
    ],

    // ── Definition des tenants ──────────────────────────────────
    // Chaque cle = ID du tenant
    // Chaque valeur = noms des variables d'env contenant les credentials
    'tenants' => [
        // 'client1' => [
        //     'host'     => 'POSTGRES_TENANT_CLIENT1_HOST',
        //     'port'     => 'POSTGRES_TENANT_CLIENT1_PORT',
        //     'db'       => 'POSTGRES_TENANT_CLIENT1_DB',
        //     'user'     => 'POSTGRES_TENANT_CLIENT1_USER',
        //     'password' => 'POSTGRES_TENANT_CLIENT1_PASSWORD',
        // ],
    ],
];
PHP;

        file_put_contents($configFile, $content);

        echo "  \033[32m✓\033[0m app/config/tenants.php\n";
        echo "\n\033[1;32m  ✓ Configuration multi-tenancy generee avec succes\033[0m\n\n";
        echo "  \033[33mProchaines etapes :\033[0m\n";
        echo "    1. Editez app/config/tenants.php pour definir vos tenants\n";
        echo "    2. Ajoutez les variables d'env correspondantes dans .env\n";
        echo "    3. Le TenantMiddleware est deja enregistre dans public/index.php\n\n";

        return 0;
    }
}
