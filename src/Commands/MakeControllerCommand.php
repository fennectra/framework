<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:controller', 'Create a new controller [--crud]')]
class MakeControllerCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:controller <Nom> [--crud]\033[0m\n";

            return 1;
        }

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $crud = isset($args['crud']);

        $dir = FENNEC_BASE_PATH . '/app/Controllers';
        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mLe controller {$name} existe déjà.\033[0m\n";

            return 1;
        }

        if ($crud) {
            $content = "<?php\n\nnamespace App\\Controllers;\n\nuse Fennec\\Attributes\\ApiDescription;\nuse Fennec\\Attributes\\ApiStatus;\nuse Fennec\\Core\\HttpException;\nuse Fennec\\Core\\Response;\n\nclass {$name}\n{\n    #[ApiDescription('Lister les éléments')]\n    #[ApiStatus(200, 'Liste retournée')]\n    public function index(): array\n    {\n        return ['status' => 'ok', 'data' => []];\n    }\n\n    #[ApiDescription('Afficher un élément')]\n    #[ApiStatus(200, 'Élément trouvé')]\n    #[ApiStatus(404, 'Élément non trouvé')]\n    public function show(string \$id): array\n    {\n        return ['status' => 'ok', 'data' => ['id' => \$id]];\n    }\n\n    #[ApiDescription('Créer un élément')]\n    #[ApiStatus(201, 'Élément créé')]\n    public function store(): array\n    {\n        return ['status' => 'ok', 'message' => 'Créé'];\n    }\n\n    #[ApiDescription('Modifier un élément')]\n    #[ApiStatus(200, 'Élément modifié')]\n    public function update(string \$id): array\n    {\n        return ['status' => 'ok', 'message' => 'Modifié', 'id' => \$id];\n    }\n\n    #[ApiDescription('Supprimer un élément')]\n    #[ApiStatus(200, 'Élément supprimé')]\n    public function delete(string \$id): array\n    {\n        return ['status' => 'ok', 'message' => 'Supprimé', 'id' => \$id];\n    }\n}\n";
        } else {
            $content = "<?php\n\nnamespace App\\Controllers;\n\nuse Fennec\\Attributes\\ApiDescription;\nuse Fennec\\Attributes\\ApiStatus;\n\nclass {$name}\n{\n    #[ApiDescription('Description de l\\'action')]\n    #[ApiStatus(200, 'Succès')]\n    public function index(): array\n    {\n        return ['status' => 'ok', 'message' => '{$name} fonctionne'];\n    }\n}\n";
        }

        file_put_contents($file, $content);

        $mode = $crud ? ' (CRUD)' : '';
        echo "\033[32m✓ Controller créé{$mode} : app/Controllers/{$name}.php\033[0m\n";

        return 0;
    }
}
