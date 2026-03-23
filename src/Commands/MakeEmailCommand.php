<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:email', 'Generate email module: migration + Model + DTOs + Controller + Routes')]
class MakeEmailCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Email Templates — Generation du module      ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migration
        $this->createMigration();

        // 2. Model
        $this->createEmailTemplateModel();

        // 3. DTOs
        $this->createDto('EmailTemplateStoreRequest', $this->dtoEmailTemplateStoreRequest());
        $this->createDto('EmailTemplateResponse', $this->dtoEmailTemplateResponse());
        $this->createDto('EmailTemplateItem', $this->dtoEmailTemplateItem());

        // 4. Controller
        $this->createController();

        // 5. Routes
        $this->createRoutes();

        // Resume
        echo "\n\033[1;32m  ✓ Module Email Templates genere avec succes\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes API (admin only) :\033[0m\n";
        echo "    GET    /email-templates              Liste paginee des templates\n";
        echo "    GET    /email-templates/{id}         Detail d'un template\n";
        echo "    POST   /email-templates              Creer un template\n";
        echo "    PUT    /email-templates/{id}         Modifier un template\n";
        echo "    DELETE /email-templates/{id}         Supprimer un template\n";

        echo "\n  \033[33mExemple de seeder :\033[0m\n";
        echo "    \033[90mEmailTemplate::create([\n";
        echo "        'name'    => 'welcome',\n";
        echo "        'locale'  => 'fr',\n";
        echo "        'subject' => 'Bienvenue {{name}} !',\n";
        echo "        'body'    => '<h1>Bienvenue {{name}}</h1><p>Votre compte est actif.</p>',\n";
        echo "    ]);\033[0m\n";

        echo "\n\033[36m  Run: ./forge migrate\033[0m\n\n";

        return 0;
    }

    // ─── Migration ─────────────────────────────────────────────

    private function createMigration(): void
    {
        $dir = FENNEC_BASE_PATH . '/database/migrations';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (glob($dir . '/*.php') as $file) {
            if (str_contains($file, 'create_email_templates')) {
                echo "  \033[33m⚠ Migration deja existante, ignoree\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_email_templates";

        $driver = Env::get('DB_DRIVER', 'pgsql');
        $content = $this->buildMigration($driver);

        file_put_contents("{$dir}/{$filename}.php", $content);
        $this->created[] = "database/migrations/{$filename}.php";
    }

    private function buildMigration(string $driver): string
    {
        $up = match ($driver) {
            'mysql' => $this->mysqlUp(),
            'sqlite' => $this->sqliteUp(),
            default => $this->pgsqlUp(),
        };

        $down = 'DROP TABLE IF EXISTS email_templates';

        $lines = [
            '<?php',
            '',
            'return [',
            '    \'up\' => \'' . str_replace("'", "\\'", $up) . '\',',
            '    \'down\' => \'' . $down . '\',',
            '];',
            '',
        ];

        return implode("\n", $lines);
    }

    // ─── Model ──────────────────────────────────────────────────

    private function createEmailTemplateModel(): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/EmailTemplate.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model EmailTemplate existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('email_templates')]
class EmailTemplate extends Model
{
    /**
     * Remplace les variables {{key}} dans le body du template.
     */
    public function render(array $vars): string
    {
        $body = $this->body;

        foreach ($vars as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    /**
     * Remplace les variables {{key}} dans le subject du template.
     */
    public function renderSubject(array $vars): string
    {
        $subject = $this->subject;

        foreach ($vars as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
        }

        return $subject;
    }

    /**
     * Recherche un template par nom et locale, avec fallback sur 'en'.
     */
    public static function findByNameAndLocale(string $name, string $locale): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM email_templates WHERE name = :name AND locale = :locale LIMIT 1',
            ['name' => $name, 'locale' => $locale]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return static::hydrate($row);
        }

        // Fallback to 'en'
        if ($locale !== 'en') {
            $stmt = DB::raw(
                'SELECT * FROM email_templates WHERE name = :name AND locale = :locale LIMIT 1',
                ['name' => $name, 'locale' => 'en']
            );

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                return static::hydrate($row);
            }
        }

        return null;
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/EmailTemplate.php';
    }

    // ─── DTOs ──────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Email";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} existe deja, ignore\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Email/{$name}.php";
    }

    private function dtoEmailTemplateStoreRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Email;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class EmailTemplateStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Nom unique du template')]
        public string $name,
        #[Description('Locale du template (fr, en, etc.)')]
        public string $locale = 'fr',
        #[Required]
        #[Description('Sujet de l\'email (supporte {{variables}})')]
        public string $subject = '',
        #[Required]
        #[Description('Corps de l\'email en HTML (supporte {{variables}})')]
        public string $body = '',
    ) {
    }
}
PHP;
    }

    private function dtoEmailTemplateResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Email;

use Fennec\Attributes\Description;

readonly class EmailTemplateResponse
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du template')]
        public string $name,
        #[Description('Locale du template')]
        public string $locale,
        #[Description('Sujet de l\'email')]
        public string $subject,
        #[Description('Corps de l\'email')]
        public string $body,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoEmailTemplateItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Email;

use Fennec\Attributes\Description;

readonly class EmailTemplateItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du template')]
        public string $name,
        #[Description('Locale du template')]
        public string $locale,
        #[Description('Sujet de l\'email')]
        public string $subject,
        #[Description('Corps de l\'email')]
        public string $body,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    // ─── Controller ────────────────────────────────────────────

    private function createController(): void
    {
        $dir = "{$this->appDir}/Controllers";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/EmailTemplateController.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller EmailTemplateController existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Email\EmailTemplateItem;
use App\Dto\Email\EmailTemplateResponse;
use App\Dto\Email\EmailTemplateStoreRequest;
use App\Models\EmailTemplate;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class EmailTemplateController
{
    #[ApiDescription('Lister les templates email', 'Retourne la liste paginee des templates email.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $data = EmailTemplate::query()
            ->orderBy('name', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'status' => 'ok',
            'data' => array_map(fn ($item) => $item->toArray(), $data),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ];
    }

    #[ApiDescription('Afficher un template email')]
    #[ApiStatus(200, 'Template trouve')]
    #[ApiStatus(404, 'Template non trouve')]
    public function show(string $id): EmailTemplateResponse
    {
        $item = EmailTemplate::findOrFail((int) $id);

        return new EmailTemplateResponse(...$item->toArray());
    }

    #[ApiDescription('Creer un template email')]
    #[ApiStatus(201, 'Template cree')]
    #[ApiStatus(422, 'Erreur de validation')]
    public function store(EmailTemplateStoreRequest $request): array
    {
        Validator::validate($request);

        $template = EmailTemplate::create([
            'name' => $request->name,
            'locale' => $request->locale,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return [
            'status' => 'ok',
            'data' => $template->toArray(),
        ];
    }

    #[ApiDescription('Modifier un template email')]
    #[ApiStatus(200, 'Template modifie')]
    #[ApiStatus(404, 'Template non trouve')]
    #[ApiStatus(422, 'Erreur de validation')]
    public function update(string $id, EmailTemplateStoreRequest $request): array
    {
        Validator::validate($request);

        $template = EmailTemplate::findOrFail((int) $id);

        $template->update([
            'name' => $request->name,
            'locale' => $request->locale,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return [
            'status' => 'ok',
            'data' => $template->toArray(),
        ];
    }

    #[ApiDescription('Supprimer un template email')]
    #[ApiStatus(200, 'Template supprime')]
    #[ApiStatus(404, 'Template non trouve')]
    public function delete(string $id): array
    {
        $template = EmailTemplate::findOrFail((int) $id);
        $template->delete();

        return [
            'status' => 'ok',
            'message' => 'Template email supprime.',
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/EmailTemplateController.php';
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $dir = "{$this->appDir}/Routes";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/email-templates.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes email-templates.php existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\EmailTemplateController;
use App\Middleware\Auth;

// ─── Admin only : Email Templates ──────────────────────────────
$router->group([
    'prefix' => '/email-templates',
    'description' => 'Email Templates — Gestion des templates email',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('', [EmailTemplateController::class, 'index']);
    $router->get('/{id}', [EmailTemplateController::class, 'show']);
    $router->post('', [EmailTemplateController::class, 'store']);
    $router->put('/{id}', [EmailTemplateController::class, 'update']);
    $router->delete('/{id}', [EmailTemplateController::class, 'delete']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/email-templates.php';
    }

    // ─── SQL Migrations ────────────────────────────────────────

    private function pgsqlUp(): string
    {
        return 'CREATE TABLE email_templates ('
            . ' id SERIAL PRIMARY KEY,'
            . ' name VARCHAR(100) NOT NULL,'
            . ' locale VARCHAR(5) NOT NULL DEFAULT \'fr\','
            . ' subject VARCHAR(255) NOT NULL,'
            . ' body TEXT NOT NULL,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE(name, locale)'
            . ')';
    }

    private function mysqlUp(): string
    {
        return 'CREATE TABLE email_templates ('
            . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' name VARCHAR(100) NOT NULL,'
            . ' locale VARCHAR(5) NOT NULL DEFAULT \'fr\','
            . ' subject VARCHAR(255) NOT NULL,'
            . ' body TEXT NOT NULL,'
            . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE KEY unique_name_locale (name, locale)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    private function sqliteUp(): string
    {
        return 'CREATE TABLE email_templates ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' name VARCHAR(100) NOT NULL,'
            . ' locale VARCHAR(5) NOT NULL DEFAULT \'fr\','
            . ' subject VARCHAR(255) NOT NULL,'
            . ' body TEXT NOT NULL,'
            . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE(name, locale)'
            . ')';
    }
}
