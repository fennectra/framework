<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;

#[Command('make:model', 'Create an ORM model [--connection=job] [--soft-delete] [--no-timestamps]')]
class MakeModelCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "\033[31mUsage: php bin/cli make:model <Nom> [--connection=job] [--soft-delete] [--no-timestamps]\033[0m\n";

            return 1;
        }

        $tableName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)) . 's';
        $connection = $args['connection'] ?? 'default';
        $softDelete = isset($args['soft-delete']);
        $noTimestamps = isset($args['no-timestamps']);

        $connectionArg = $connection !== 'default' ? ", '{$connection}'" : '';

        $dir = FENNEC_BASE_PATH . '/app/Models';
        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "\033[31mLe model {$name} existe déjà.\033[0m\n";

            return 1;
        }

        // Build des propriétés statiques
        $props = [];
        if ($noTimestamps) {
            $props[] = '    protected static bool $timestamps = false;';
        }
        if ($softDelete) {
            $props[] = '    protected static bool $softDeletes = true;';
        }
        $propsBlock = !empty($props) ? "\n" . implode("\n", $props) . "\n" : '';

        // Build des casts exemple
        $castsBlock = "\n    /** @var array<string, string> */\n    protected static array \$casts = [\n        // 'is_active' => 'bool',\n        // 'metadata' => 'json',\n    ];\n";

        $content = <<<PHP
<?php

namespace App\\Models;

use Fennec\\Attributes\\Table;
use Fennec\\Core\\Model;

#[Table('{$tableName}'{$connectionArg})]
class {$name} extends Model
{{$propsBlock}{$castsBlock}
    // Relations — exemples :
    //
    // public function posts(): \\Fennec\\Core\\Collection
    // {
    //     return \$this->hasMany(Post::class);
    // }
    //
    // public function role(): ?\\Fennec\\Core\\Model
    // {
    //     return \$this->belongsTo(Role::class, 'role_id');
    // }
}

PHP;

        file_put_contents($file, $content);

        echo "\033[32m✓ Model ORM créé : app/Models/{$name}.php\033[0m\n";
        echo "  Table : {$tableName}\n";
        if ($connection !== 'default') {
            echo "  Connection : {$connection}\n";
        }
        if ($softDelete) {
            echo "  Soft delete : activé\n";
        }
        if ($noTimestamps) {
            echo "  Timestamps : désactivé\n";
        }

        echo "\n  \033[33mUsage :\033[0m\n";
        echo "    \$item = {$name}::find(1);\n";
        echo "    \$item->name = 'Nouveau';\n";
        echo "    \$item->save();\n";
        echo "    {$name}::create(['name' => 'Test']);\n";
        echo "    {$name}::where('active', true)->get();\n";
        echo "    {$name}::paginate(20, 1);\n";

        return 0;
    }
}
