<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:webhook', 'Generate webhook module: migration + Models + DTOs + Controller + Routes')]
class MakeWebhookCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Webhook — Generation du module complet     ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migration
        $this->createMigration();

        // 2. Models
        $this->createWebhookModel();
        $this->createWebhookDeliveryModel();

        // 3. DTOs
        $this->createDto('WebhookItem', $this->dtoWebhookItem());
        $this->createDto('WebhookStoreRequest', $this->dtoWebhookStoreRequest());
        $this->createDto('WebhookResponse', $this->dtoWebhookResponse());
        $this->createDto('WebhookListRequest', $this->dtoWebhookListRequest());
        $this->createDto('WebhookDeliveryItem', $this->dtoWebhookDeliveryItem());

        // 4. Controller
        $this->createController();

        // 5. Routes
        $this->createRoutes();

        // Resume
        echo "\n\033[1;32m  ✓ Module Webhook genere avec succes\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes API (admin) :\033[0m\n";
        echo "    GET    /webhooks                          Lister les webhooks\n";
        echo "    GET    /webhooks/{id}                     Detail d'un webhook\n";
        echo "    POST   /webhooks                          Creer un webhook\n";
        echo "    PUT    /webhooks/{id}                     Modifier un webhook\n";
        echo "    DELETE /webhooks/{id}                     Supprimer un webhook\n";
        echo "    PATCH  /webhooks/{id}/toggle              Activer/desactiver\n";
        echo "    GET    /webhooks/{id}/deliveries          Livraisons d'un webhook\n";
        echo "    GET    /webhooks/stats                    Statistiques de livraison\n";
        echo "    GET    /webhooks/failures                 Echecs recents\n";
        echo "    POST   /webhooks/deliveries/{id}/retry    Relancer une livraison\n";
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
            if (str_contains($file, 'create_webhooks_tables')) {
                echo "  \033[33m⚠ Migration deja existante, ignoree\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_webhooks_tables";

        $driver = Env::get('DB_DRIVER', 'pgsql');
        $up = match ($driver) {
            'mysql' => $this->mysqlUp(),
            'sqlite' => $this->sqliteUp(),
            default => $this->pgsqlUp(),
        };

        $down = 'DROP TABLE IF EXISTS webhook_deliveries; DROP TABLE IF EXISTS webhooks';

        $content = implode("\n", [
            '<?php',
            '',
            'return [',
            '    \'up\' => \'' . str_replace("'", "\\'", $up) . '\',',
            '    \'down\' => \'' . $down . '\',',
            '];',
            '',
        ]);

        file_put_contents("{$dir}/{$filename}.php", $content);
        $this->created[] = "database/migrations/{$filename}.php";
    }

    // ─── Models ────────────────────────────────────────────────

    private function createWebhookModel(): void
    {
        $file = "{$this->appDir}/Models/Webhook.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model Webhook existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\HasMany;

#[Table('webhooks')]
class Webhook extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'is_active' => 'bool',
        'events' => 'json',
    ];

    /**
     * Les livraisons liees a ce webhook.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }

    /**
     * Retourne les webhooks actifs abonnes a un event donne.
     *
     * @return array<int, static>
     */
    public static function activeForEvent(string $event): array
    {
        $all = static::where('is_active', '=', true)->get();
        $matched = [];

        foreach ($all as $webhook) {
            $events = $webhook->getAttribute('events');
            if (is_string($events)) {
                $events = json_decode($events, true) ?: [];
            }
            if (in_array('*', $events, true) || in_array($event, $events, true)) {
                $matched[] = $webhook;
            }
        }

        return $matched;
    }

    /**
     * Statistiques de livraison groupees par webhook.
     */
    public static function stats(): array
    {
        $stmt = DB::raw(
            'SELECT w.id, w.name, w.url, w.is_active,
                    COUNT(wd.id) as total_deliveries,
                    COUNT(CASE WHEN wd.status = \'delivered\' THEN 1 END) as delivered,
                    COUNT(CASE WHEN wd.status = \'failed\' THEN 1 END) as failed,
                    COUNT(CASE WHEN wd.status = \'pending\' THEN 1 END) as pending
             FROM webhooks w
             LEFT JOIN webhook_deliveries wd ON wd.webhook_id = w.id
             GROUP BY w.id, w.name, w.url, w.is_active
             ORDER BY w.name'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/Webhook.php';
    }

    private function createWebhookDeliveryModel(): void
    {
        $file = "{$this->appDir}/Models/WebhookDelivery.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model WebhookDelivery existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\BelongsTo;

#[Table('webhook_deliveries')]
class WebhookDelivery extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'webhook_id' => 'int',
        'http_status' => 'int',
        'attempt' => 'int',
    ];

    /**
     * Le webhook parent.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class, 'webhook_id');
    }

    /**
     * Retourne les dernieres livraisons echouees.
     */
    public static function recentFailures(int $limit = 20): array
    {
        $stmt = DB::raw(
            'SELECT wd.id, wd.webhook_id, wd.event, wd.url, wd.status,
                    wd.http_status, wd.attempt, wd.response_body, wd.created_at,
                    w.name as webhook_name
             FROM webhook_deliveries wd
             LEFT JOIN webhooks w ON w.id = wd.webhook_id
             WHERE wd.status = \'failed\'
             ORDER BY wd.created_at DESC
             LIMIT :limit',
            ['limit' => $limit]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Vue d'ensemble des totaux par statut (pending, delivered, failed).
     */
    public static function statsOverview(): array
    {
        $stmt = DB::raw(
            'SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN status = \'pending\' THEN 1 END) as pending,
                COUNT(CASE WHEN status = \'delivered\' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed
             FROM webhook_deliveries'
        );

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total' => 0,
            'pending' => 0,
            'delivered' => 0,
            'failed' => 0,
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/WebhookDelivery.php';
    }

    // ─── DTOs ──────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Webhook";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} existe deja, ignore\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Webhook/{$name}.php";
    }

    private function dtoWebhookItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du webhook')]
        public string $name,
        #[Description('URL de destination')]
        public string $url,
        #[Description('Secret HMAC-SHA256')]
        public string $secret,
        #[Description('Events auxquels le webhook est abonne')]
        public array $events,
        #[Description('Webhook actif')]
        public bool $is_active,
        #[Description('Description du webhook')]
        public ?string $description = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
        #[Description('Date de mise a jour')]
        public ?string $updated_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoWebhookStoreRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;
use Fennec\Attributes\MaxLength;
use Fennec\Attributes\Required;

readonly class WebhookStoreRequest
{
    public function __construct(
        #[Required]
        #[MaxLength(255)]
        #[Description('Nom du webhook')]
        public string $name = '',
        #[Required]
        #[MaxLength(2048)]
        #[Description('URL de destination')]
        public string $url = '',
        #[Description('Secret HMAC-SHA256 (genere automatiquement si absent)')]
        public ?string $secret = null,
        #[Description('Events auxquels le webhook est abonne')]
        public array $events = [],
        #[Description('Webhook actif')]
        public bool $is_active = true,
        #[Description('Description du webhook')]
        public ?string $description = null,
    ) {
    }
}
PHP;
    }

    private function dtoWebhookResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Webhook')]
        public ?WebhookItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}
PHP;
    }

    private function dtoWebhookListRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class WebhookListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par statut actif')]
        public ?bool $is_active = null,
    ) {
    }
}
PHP;
    }

    private function dtoWebhookDeliveryItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookDeliveryItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('ID du webhook')]
        public int $webhook_id,
        #[Description('Event declenche')]
        public string $event,
        #[Description('URL de destination')]
        public string $url,
        #[Description('Statut de la livraison')]
        public string $status,
        #[Description('Code HTTP retourne')]
        public int $http_status,
        #[Description('Numero de tentative')]
        public int $attempt,
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
        $file = "{$this->appDir}/Controllers/WebhookController.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller WebhookController existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Webhook\WebhookDeliveryItem;
use App\Dto\Webhook\WebhookItem;
use App\Dto\Webhook\WebhookListRequest;
use App\Dto\Webhook\WebhookResponse;
use App\Dto\Webhook\WebhookStoreRequest;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Queue\Job;
use Fennec\Core\Webhook\WebhookDeliveryJob;
use Fennec\Core\Webhook\WebhookManager;

class WebhookController
{
    // ─── CRUD ──────────────────────────────────────────────────

    #[ApiDescription('Lister les webhooks', 'Retourne la liste paginee des webhooks.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(WebhookListRequest $request): array
    {
        if ($request->is_active !== null) {
            $items = Webhook::where('is_active', '=', $request->is_active)
                ->orderBy('created_at', 'DESC')
                ->get();

            return [
                'data' => array_map(fn ($item) => $item->toArray(), $items),
                'meta' => ['total' => count($items)],
            ];
        }

        return Webhook::paginate($request->limit, $request->page);
    }

    #[ApiDescription('Afficher un webhook')]
    #[ApiStatus(200, 'Webhook trouve')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function show(string $id): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Creer un webhook')]
    #[ApiStatus(201, 'Webhook cree')]
    public function store(WebhookStoreRequest $input): WebhookResponse
    {
        $secret = $input->secret ?? bin2hex(random_bytes(32));

        $item = Webhook::create([
            'name' => $input->name,
            'url' => $input->url,
            'secret' => $secret,
            'events' => json_encode($input->events),
            'is_active' => $input->is_active,
            'description' => $input->description,
        ]);

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: 'Webhook cree avec succes',
        );
    }

    #[ApiDescription('Modifier un webhook')]
    #[ApiStatus(200, 'Webhook modifie')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function update(string $id, WebhookStoreRequest $input): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);

        $data = [
            'name' => $input->name,
            'url' => $input->url,
            'events' => json_encode($input->events),
            'is_active' => $input->is_active,
            'description' => $input->description,
        ];

        if ($input->secret !== null) {
            $data['secret'] = $input->secret;
        }

        $item->fill($data)->save();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: 'Webhook modifie avec succes',
        );
    }

    #[ApiDescription('Supprimer un webhook')]
    #[ApiStatus(200, 'Webhook supprime')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function delete(string $id): array
    {
        $item = Webhook::findOrFail((int) $id);
        $item->delete();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return [
            'status' => 'ok',
            'message' => 'Webhook supprime avec succes',
        ];
    }

    #[ApiDescription('Activer ou desactiver un webhook')]
    #[ApiStatus(200, 'Statut modifie')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function toggle(string $id): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);
        $newStatus = !$item->getAttribute('is_active');
        $item->fill(['is_active' => $newStatus])->save();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: $newStatus ? 'Webhook active' : 'Webhook desactive',
        );
    }

    // ─── Deliveries ────────────────────────────────────────────

    #[ApiDescription('Lister les livraisons d\'un webhook')]
    #[ApiStatus(200, 'Liste retournee')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function deliveries(string $id): array
    {
        $webhook = Webhook::findOrFail((int) $id);
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $deliveries = WebhookDelivery::where('webhook_id', '=', (int) $id)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'data' => array_map(fn ($d) => $d->toArray(), $deliveries),
            'meta' => [
                'webhook_id' => (int) $id,
                'webhook_name' => $webhook->getAttribute('name'),
                'page' => $page,
                'limit' => $limit,
            ],
        ];
    }

    #[ApiDescription('Statistiques de livraison', 'Vue d\'ensemble des livraisons par webhook et totaux globaux.')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function deliveryStats(): array
    {
        return [
            'status' => 'ok',
            'data' => [
                'overview' => WebhookDelivery::statsOverview(),
                'by_webhook' => Webhook::stats(),
            ],
        ];
    }

    #[ApiDescription('Echecs recents', 'Liste des dernieres livraisons echouees.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function recentFailures(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);

        return [
            'status' => 'ok',
            'data' => WebhookDelivery::recentFailures($limit),
        ];
    }

    #[ApiDescription('Relancer une livraison echouee')]
    #[ApiStatus(200, 'Livraison relancee')]
    #[ApiStatus(404, 'Livraison non trouvee')]
    #[ApiStatus(422, 'Livraison non echouee')]
    public function retry(string $id): array
    {
        $delivery = WebhookDelivery::findOrFail((int) $id);

        if ($delivery->getAttribute('status') !== 'failed') {
            throw new HttpException(422, 'Seules les livraisons echouees peuvent etre relancees');
        }

        $webhook = Webhook::findOrFail((int) $delivery->getAttribute('webhook_id'));

        Job::dispatch(WebhookDeliveryJob::class, [
            'webhook_id' => (int) $webhook->getAttribute('id'),
            'url' => $webhook->getAttribute('url'),
            'secret' => $webhook->getAttribute('secret'),
            'event' => $delivery->getAttribute('event'),
            'payload' => json_decode($delivery->getAttribute('payload') ?? '{}', true),
            'attempt' => 0,
        ], 'webhooks');

        return [
            'status' => 'ok',
            'message' => 'Livraison relancee via la queue webhooks',
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/WebhookController.php';
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $file = "{$this->appDir}/Routes/webhook.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes webhook.php existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\WebhookController;
use App\Middleware\Auth;

// ─── Admin : gestion des webhooks ──────────────────────────────
$router->group([
    'prefix' => '/webhooks',
    'description' => 'Webhooks — Gestion des webhooks sortants (admin)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    // Stats et failures avant les routes parametrees pour eviter les conflits
    $router->get('/stats', [WebhookController::class, 'deliveryStats']);
    $router->get('/failures', [WebhookController::class, 'recentFailures']);

    // CRUD
    $router->get('', [WebhookController::class, 'index']);
    $router->post('', [WebhookController::class, 'store']);
    $router->get('/{id}', [WebhookController::class, 'show']);
    $router->put('/{id}', [WebhookController::class, 'update']);
    $router->delete('/{id}', [WebhookController::class, 'delete']);

    // Actions
    $router->patch('/{id}/toggle', [WebhookController::class, 'toggle']);
    $router->get('/{id}/deliveries', [WebhookController::class, 'deliveries']);

    // Retry delivery
    $router->post('/deliveries/{id}/retry', [WebhookController::class, 'retry']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/webhook.php';
    }

    // ─── SQL Migrations ────────────────────────────────────────

    private function pgsqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS webhooks ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' name VARCHAR(255) NOT NULL,'
            . ' url VARCHAR(2048) NOT NULL,'
            . ' secret VARCHAR(255) NOT NULL,'
            . ' events JSONB NOT NULL DEFAULT \'[]\'::jsonb,'
            . ' is_active BOOLEAN NOT NULL DEFAULT TRUE,'
            . ' description TEXT,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_webhooks_is_active ON webhooks (is_active);'
            . ' CREATE TABLE IF NOT EXISTS webhook_deliveries ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' webhook_id BIGINT NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,'
            . ' event VARCHAR(255) NOT NULL,'
            . ' url VARCHAR(2048) NOT NULL,'
            . ' payload JSONB,'
            . ' status VARCHAR(50) NOT NULL DEFAULT \'pending\','
            . ' http_status INTEGER DEFAULT 0,'
            . ' response_body TEXT,'
            . ' attempt INTEGER NOT NULL DEFAULT 1,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook_id ON webhook_deliveries (webhook_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_status ON webhook_deliveries (status)';
    }

    private function mysqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS webhooks ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' name VARCHAR(255) NOT NULL,'
            . ' url VARCHAR(2048) NOT NULL,'
            . ' secret VARCHAR(255) NOT NULL,'
            . ' events JSON NOT NULL,'
            . ' is_active TINYINT(1) NOT NULL DEFAULT 1,'
            . ' description TEXT,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
            . ' INDEX idx_webhooks_is_active (is_active)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            . ' CREATE TABLE IF NOT EXISTS webhook_deliveries ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' webhook_id BIGINT UNSIGNED NOT NULL,'
            . ' event VARCHAR(255) NOT NULL,'
            . ' url VARCHAR(2048) NOT NULL,'
            . ' payload JSON DEFAULT NULL,'
            . ' status VARCHAR(50) NOT NULL DEFAULT \'pending\','
            . ' http_status INT DEFAULT 0,'
            . ' response_body TEXT,'
            . ' attempt INT NOT NULL DEFAULT 1,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' INDEX idx_webhook_deliveries_webhook_id (webhook_id),'
            . ' INDEX idx_webhook_deliveries_status (status),'
            . ' CONSTRAINT fk_wd_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    private function sqliteUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS webhooks ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' name TEXT NOT NULL,'
            . ' url TEXT NOT NULL,'
            . ' secret TEXT NOT NULL,'
            . ' events TEXT NOT NULL DEFAULT \'[]\','
            . ' is_active INTEGER NOT NULL DEFAULT 1,'
            . ' description TEXT,'
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS webhook_deliveries ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' webhook_id INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,'
            . ' event TEXT NOT NULL,'
            . ' url TEXT NOT NULL,'
            . ' payload TEXT,'
            . ' status TEXT NOT NULL DEFAULT \'pending\','
            . ' http_status INTEGER DEFAULT 0,'
            . ' response_body TEXT,'
            . ' attempt INTEGER NOT NULL DEFAULT 1,'
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ')';
    }
}
