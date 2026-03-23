<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:dto', 'Create a new DTO [--request] [--response]')]
class MakeDtoCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:dto <Nom> [--request] [--response]\033[0m\n";
            echo "\033[33m  Subdirectory support: make:dto Auth/LoginRequest --request\033[0m\n";

            return 1;
        }

        $isRequest = isset($args['request']);
        $isResponse = isset($args['response']);

        // Support subdirectory via name format: Subdomain/ClassName
        $subdomain = '';
        $className = $name;

        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $subdomain = implode('/', $parts);
        }

        $namespace = $subdomain ? "App\\Dto\\{$subdomain}" : 'App\\Dto';
        $dir = FENNEC_BASE_PATH . '/app/Dto' . ($subdomain ? "/{$subdomain}" : '');
        $file = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            echo "\033[31mLe DTO {$className} existe déjà.\033[0m\n";

            return 1;
        }

        $nsEscaped = str_replace('\\', '\\\\', $namespace);

        if ($isRequest) {
            $content = "<?php\n\nnamespace {$nsEscaped};\n\nuse Fennec\\Attributes\\Description;\nuse Fennec\\Attributes\\Required;\nuse Fennec\\Attributes\\Email;\nuse Fennec\\Attributes\\MinLength;\n\nreadonly class {$className}\n{\n    public function __construct(\n        #[Required]\n        #[Description('Nom')]\n        public string \$name,\n    ) {}\n}\n";
        } elseif ($isResponse) {
            $content = "<?php\n\nnamespace {$nsEscaped};\n\nuse Fennec\\Attributes\\Description;\n\nreadonly class {$className}\n{\n    public function __construct(\n        #[Description('Statut de la réponse')]\n        public string \$status,\n\n        #[Description('Données')]\n        public array \$data = [],\n\n        #[Description('Message')]\n        public string \$message = '',\n    ) {}\n}\n";
        } else {
            $content = "<?php\n\nnamespace {$nsEscaped};\n\nuse Fennec\\Attributes\\Description;\n\nreadonly class {$className}\n{\n    public function __construct(\n        #[Description('Statut de la réponse')]\n        public string \$status,\n    ) {}\n}\n";
        }

        file_put_contents($file, $content);

        $relativePath = $subdomain ? "app/Dto/{$subdomain}/{$className}.php" : "app/Dto/{$className}.php";
        $type = $isRequest ? ' (request)' : ($isResponse ? ' (response)' : '');
        echo "\033[32m✓ DTO créé{$type} : {$relativePath}\033[0m\n";

        return 0;
    }
}
