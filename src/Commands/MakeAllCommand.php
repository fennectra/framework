<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:all', 'Generate Controller + DTO (request/response) + Model + Routes')]
class MakeAllCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:all <Nom> [--connection=job] [--no-auth] [--roles=admin,manager]\033[0m\n";

            return 1;
        }

        $connection = $args['connection'] ?? 'default';
        $noAuth = isset($args['no-auth']);
        $roles = $args['roles'] ?? null;

        // Support subdirectory via name format: Subdomain/ClassName
        $subdomain = '';
        $baseName = $name;

        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $baseName = array_pop($parts);
            $subdomain = implode('/', $parts);
        }

        // Noms dérivés
        $controllerName = $baseName . 'Controller';
        $requestDto = $baseName . 'Request';
        $responseDto = $baseName . 'Response';
        $modelName = $baseName;
        $tableName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $baseName)) . 's';
        $routeFileName = strtolower($baseName);
        $prefix = '/' . $routeFileName;
        $varName = lcfirst($baseName);

        $baseDir = FENNEC_BASE_PATH . '/app';
        $created = [];

        // 1. Model
        $this->createModel($baseDir, $modelName, $tableName, $connection, $created);

        // 2. DTO Request
        $this->createRequestDto($baseDir, $requestDto, $created, $subdomain);

        // 3. DTO Response
        $this->createResponseDto($baseDir, $responseDto, $created, $subdomain);

        // 4. Controller (CRUD)
        $this->createController($baseDir, $controllerName, $modelName, $requestDto, $responseDto, $tableName, $created, $subdomain);

        // 5. Routes
        $this->createRoutes($baseDir, $routeFileName, $controllerName, $prefix, $noAuth, $roles, $created);

        // Résumé
        echo "\n\033[1;36m  ╔══════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Génération terminée : {$name}       \033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════╝\033[0m\n\n";

        foreach ($created as $file) {
            echo "  \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes CRUD :\033[0m\n";
        echo "    GET    {$prefix}         → {$controllerName}@index\n";
        echo "    GET    {$prefix}/{id}    → {$controllerName}@show\n";
        echo "    POST   {$prefix}         → {$controllerName}@store\n";
        echo "    PUT    {$prefix}/{id}    → {$controllerName}@update\n";
        echo "    DELETE {$prefix}/{id}    → {$controllerName}@delete\n";
        echo "\n";

        return 0;
    }

    private function createModel(string $baseDir, string $name, string $table, string $connection, array &$created): void
    {
        $file = "{$baseDir}/Models/{$name}.php";
        if (file_exists($file)) {
            echo "\033[33m⚠ Model {$name} existe déjà, ignoré\033[0m\n";

            return;
        }

        $connArg = $connection !== 'default' ? ", '{$connection}'" : '';

        $content = "<?php\n\nnamespace App\\Models;\n\nuse Fennec\\Attributes\\Table;\nuse Fennec\\Core\\Model;\n\n#[Table('{$table}'{$connArg})]\nclass {$name} extends Model\n{\n    /** @var array<string, string> */\n    protected static array \$casts = [\n        // 'is_active' => 'bool',\n    ];\n}\n";

        file_put_contents($file, $content);
        $created[] = "app/Models/{$name}.php";
    }

    private function createRequestDto(string $baseDir, string $name, array &$created, string $subdomain = ''): void
    {
        $dir = $subdomain ? "{$baseDir}/Dto/{$subdomain}" : "{$baseDir}/Dto";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";
        if (file_exists($file)) {
            echo "\033[33m⚠ DTO {$name} existe déjà, ignoré\033[0m\n";

            return;
        }

        $namespace = $subdomain ? "App\\Dto\\{$subdomain}" : 'App\\Dto';
        $content = "<?php\n\nnamespace {$namespace};\n\nuse Fennec\\Attributes\\Description;\nuse Fennec\\Attributes\\Min;\n\nreadonly class {$name}\n{\n    public function __construct(\n        #[Description('Nombre d\\'éléments par page')]\n        #[Min(1)]\n        public int \$limit = 20,\n\n        #[Description('Numéro de page')]\n        #[Min(1)]\n        public int \$page = 1,\n    ) {}\n}\n";

        file_put_contents($file, $content);
        $relativePath = $subdomain ? "app/Dto/{$subdomain}/{$name}.php" : "app/Dto/{$name}.php";
        $created[] = $relativePath;
    }

    private function createResponseDto(string $baseDir, string $name, array &$created, string $subdomain = ''): void
    {
        $dir = $subdomain ? "{$baseDir}/Dto/{$subdomain}" : "{$baseDir}/Dto";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";
        if (file_exists($file)) {
            echo "\033[33m⚠ DTO {$name} existe déjà, ignoré\033[0m\n";

            return;
        }

        $namespace = $subdomain ? "App\\Dto\\{$subdomain}" : 'App\\Dto';
        $content = "<?php\n\nnamespace {$namespace};\n\nuse Fennec\\Attributes\\Description;\n\nreadonly class {$name}\n{\n    public function __construct(\n        #[Description('Statut de la réponse')]\n        public string \$status,\n\n        #[Description('Données')]\n        public array \$data = [],\n\n        #[Description('Message')]\n        public string \$message = '',\n    ) {}\n}\n";

        file_put_contents($file, $content);
        $relativePath = $subdomain ? "app/Dto/{$subdomain}/{$name}.php" : "app/Dto/{$name}.php";
        $created[] = $relativePath;
    }

    private function createController(string $baseDir, string $controllerName, string $model, string $requestDto, string $responseDto, string $table, array &$created, string $subdomain = ''): void
    {
        $file = "{$baseDir}/Controllers/{$controllerName}.php";
        if (file_exists($file)) {
            echo "\033[33m⚠ Controller {$controllerName} existe déjà, ignoré\033[0m\n";

            return;
        }

        $dtoNamespace = $subdomain ? "App\\Dto\\{$subdomain}" : 'App\\Dto';
        $content = "<?php\n\nnamespace App\\Controllers;\n\nuse Fennec\\Attributes\\ApiDescription;\nuse Fennec\\Attributes\\ApiStatus;\nuse Fennec\\Core\\HttpException;\nuse Fennec\\Core\\Response;\nuse {$dtoNamespace}\\{$requestDto};\nuse {$dtoNamespace}\\{$responseDto};\nuse App\\Models\\{$model};\n\nclass {$controllerName}\n{\n    #[ApiDescription('Lister les {$table} (paginé)')]\n    #[ApiStatus(200, 'Liste retournée')]\n    public function index({$requestDto} \$input): array\n    {\n        return {$model}::paginate(\n            perPage: \$input->limit ?? 20,\n            page: \$input->page ?? 1,\n        );\n    }\n\n    #[ApiDescription('Afficher un {$table} par ID')]\n    #[ApiStatus(200, 'Élément trouvé')]\n    #[ApiStatus(404, 'Élément non trouvé')]\n    public function show(string \$id): {$responseDto}\n    {\n        \$item = {$model}::findOrFail((int) \$id);\n        return new {$responseDto}(status: 'ok', data: \$item->toArray());\n    }\n\n    #[ApiDescription('Créer un {$table}')]\n    #[ApiStatus(201, 'Élément créé')]\n    public function store({$requestDto} \$input): {$responseDto}\n    {\n        \$item = {$model}::create((array) \$input);\n        return new {$responseDto}(status: 'ok', data: \$item->toArray(), message: '{$model} créé');\n    }\n\n    #[ApiDescription('Modifier un {$table}')]\n    #[ApiStatus(200, 'Élément modifié')]\n    #[ApiStatus(404, 'Élément non trouvé')]\n    public function update(string \$id, {$requestDto} \$input): {$responseDto}\n    {\n        \$item = {$model}::findOrFail((int) \$id);\n        \$item->fill((array) \$input)->save();\n        return new {$responseDto}(status: 'ok', data: \$item->toArray(), message: '{$model} modifié');\n    }\n\n    #[ApiDescription('Supprimer un {$table}')]\n    #[ApiStatus(200, 'Élément supprimé')]\n    #[ApiStatus(404, 'Élément non trouvé')]\n    public function delete(string \$id): {$responseDto}\n    {\n        \$item = {$model}::findOrFail((int) \$id);\n        \$item->delete();\n        return new {$responseDto}(status: 'ok', message: '{$model} supprimé');\n    }\n}\n";

        file_put_contents($file, $content);
        $created[] = "app/Controllers/{$controllerName}.php";
    }

    private function createRoutes(string $baseDir, string $fileName, string $controllerName, string $prefix, bool $noAuth, ?string $roles, array &$created): void
    {
        $file = "{$baseDir}/Routes/{$fileName}.php";
        if (file_exists($file)) {
            echo "\033[33m⚠ Routes {$fileName}.php existe déjà, ignoré\033[0m\n";

            return;
        }

        $lines = ["<?php\n", "use App\\Controllers\\{$controllerName};"];

        if (!$noAuth) {
            $lines[] = 'use App\\Middleware\\Auth;';
        }

        $lines[] = '';

        if ($noAuth) {
            // Routes publiques sans group
            $lines[] = "\$router->get('{$prefix}', [{$controllerName}::class, 'index']);";
            $lines[] = "\$router->get('{$prefix}/{id}', [{$controllerName}::class, 'show']);";
            $lines[] = "\$router->post('{$prefix}', [{$controllerName}::class, 'store']);";
            $lines[] = "\$router->put('{$prefix}/{id}', [{$controllerName}::class, 'update']);";
            $lines[] = "\$router->delete('{$prefix}/{id}', [{$controllerName}::class, 'delete']);";
        } else {
            $rolesArray = $roles ? "'" . implode("', '", explode(',', $roles)) . "'" : "'admin'";
            $lines[] = '$router->group([';
            $lines[] = "    'prefix' => '{$prefix}',";
            $lines[] = "    'description' => '" . ucfirst($fileName) . " — CRUD API',";
            $lines[] = "    'middleware' => [[Auth::class, [{$rolesArray}]]],";
            $lines[] = '], function ($router) {';
            $lines[] = "    \$router->get('', [{$controllerName}::class, 'index']);";
            $lines[] = "    \$router->get('/{id}', [{$controllerName}::class, 'show']);";
            $lines[] = "    \$router->post('', [{$controllerName}::class, 'store']);";
            $lines[] = "    \$router->put('/{id}', [{$controllerName}::class, 'update']);";
            $lines[] = "    \$router->delete('/{id}', [{$controllerName}::class, 'delete']);";
            $lines[] = '});';
        }

        $lines[] = '';

        file_put_contents($file, implode("\n", $lines));
        $created[] = "app/Routes/{$fileName}.php";
    }
}
