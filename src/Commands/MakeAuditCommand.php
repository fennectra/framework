<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:audit', 'Generate audit module: migration + Model + DTOs + Controller + Routes')]
class MakeAuditCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Audit Trail — Generation du module SOC 2   ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migration
        $this->createMigration();

        // 2. Model
        $this->createAuditLogModel();

        // 3. DTOs
        $this->createDto('AuditLogItem', $this->dtoAuditLogItem());
        $this->createDto('AuditLogListRequest', $this->dtoAuditLogListRequest());
        $this->createDto('AuditLogResponse', $this->dtoAuditLogResponse());

        // 4. Controller
        $this->createController();

        // 5. Routes
        $this->createRoutes();

        // Resume
        echo "\n\033[1;32m  ✓ Module Audit Trail genere avec succes\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes API (admin only — SOC 2) :\033[0m\n";
        echo "    GET    /audit                              Liste paginee avec filtres\n";
        echo "    GET    /audit/{id}                         Detail d'une entree\n";
        echo "    GET    /audit/entity/{type}/{entityId}     Logs d'une entite\n";
        echo "    GET    /audit/users/{userId}               Logs d'un utilisateur\n";
        echo "    GET    /audit/stats                        Statistiques par action\n";
        echo "    GET    /audit/recent                       Activite recente\n";
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
            if (str_contains($file, 'create_audit_logs')) {
                echo "  \033[33m⚠ Migration deja existante, ignoree\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_audit_logs";

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

        $down = 'DROP TABLE IF EXISTS audit_logs';

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

    private function createAuditLogModel(): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/AuditLog.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model AuditLog existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('audit_logs')]
class AuditLog extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    /**
     * Retourne les logs d'audit pour une entite donnee.
     */
    public static function forEntity(string $type, int $id): array
    {
        return static::where('auditable_type', '=', $type)
            ->where('auditable_id', '=', $id)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Retourne les logs d'audit pour un utilisateur donne.
     */
    public static function byUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = DB::raw(
            'SELECT * FROM audit_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['user_id' => $userId, 'limit' => $limit, 'offset' => $offset]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les statistiques par type d'action.
     */
    public static function stats(): array
    {
        $stmt = DB::raw(
            'SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action ORDER BY count DESC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les dernieres entrees d'audit.
     */
    public static function recentActivity(int $limit = 20): array
    {
        $stmt = DB::raw(
            'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :limit',
            ['limit' => $limit]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche avec filtres (action, auditable_type, user_id, date_from, date_to).
     */
    public static function search(array $filters, int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['auditable_type'])) {
            $where[] = 'auditable_type = :auditable_type';
            $params['auditable_type'] = $filters['auditable_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT * FROM audit_logs';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = DB::raw($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/AuditLog.php';
    }

    // ─── DTOs ──────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Audit";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} existe deja, ignore\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Audit/{$name}.php";
    }

    private function dtoAuditLogItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class AuditLogItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Type de l\'entite auditee')]
        public string $auditable_type,
        #[Description('ID de l\'entite auditee')]
        public int $auditable_id,
        #[Description('Action effectuee (created/updated/deleted)')]
        public string $action,
        #[Description('Anciennes valeurs')]
        public mixed $old_values = null,
        #[Description('Nouvelles valeurs')]
        public mixed $new_values = null,
        #[Description('ID de l\'utilisateur')]
        public ?int $user_id = null,
        #[Description('Adresse IP')]
        public ?string $ip_address = null,
        #[Description('ID de la requete')]
        public ?string $request_id = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoAuditLogListRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class AuditLogListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par action (created/updated/deleted)')]
        public ?string $action = null,
        #[Description('Filtrer par type d\'entite')]
        public ?string $auditable_type = null,
        #[Description('Filtrer par utilisateur')]
        public ?int $user_id = null,
        #[Description('Date de debut (YYYY-MM-DD)')]
        public ?string $date_from = null,
        #[Description('Date de fin (YYYY-MM-DD)')]
        public ?string $date_to = null,
    ) {
    }
}
PHP;
    }

    private function dtoAuditLogResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class AuditLogResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Entree d\'audit')]
        public ?AuditLogItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
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

        $file = "{$dir}/AuditController.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller AuditController existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Audit\AuditLogItem;
use App\Dto\Audit\AuditLogListRequest;
use App\Dto\Audit\AuditLogResponse;
use App\Models\AuditLog;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;

class AuditController
{
    #[ApiDescription('Lister les logs d\'audit', 'Retourne la liste paginee des logs d\'audit avec filtres.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(AuditLogListRequest $request): array
    {
        $filters = [];

        if ($request->action !== null) {
            $filters['action'] = $request->action;
        }

        if ($request->auditable_type !== null) {
            $filters['auditable_type'] = $request->auditable_type;
        }

        if ($request->user_id !== null) {
            $filters['user_id'] = $request->user_id;
        }

        if ($request->date_from !== null) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->date_to !== null) {
            $filters['date_to'] = $request->date_to;
        }

        $offset = ($request->page - 1) * $request->limit;
        $data = AuditLog::search($filters, $request->limit, $offset);

        return [
            'status' => 'ok',
            'data' => $data,
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
            ],
        ];
    }

    #[ApiDescription('Afficher un log d\'audit')]
    #[ApiStatus(200, 'Entree trouvee')]
    #[ApiStatus(404, 'Entree non trouvee')]
    public function show(string $id): AuditLogResponse
    {
        $item = AuditLog::findOrFail((int) $id);

        return new AuditLogResponse(
            status: 'ok',
            data: new AuditLogItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Logs d\'audit pour une entite', 'Retourne tous les logs d\'audit pour un type et ID d\'entite donnes.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function forEntity(string $type, string $entityId): array
    {
        $data = AuditLog::forEntity($type, (int) $entityId);

        return [
            'status' => 'ok',
            'data' => array_map(fn ($item) => $item->toArray(), $data),
        ];
    }

    #[ApiDescription('Logs d\'audit par utilisateur', 'Retourne les logs d\'audit pour un utilisateur donne.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function byUser(string $userId): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $offset = (int) ($_GET['offset'] ?? 0);

        $data = AuditLog::byUser((int) $userId, $limit, $offset);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Statistiques d\'audit', 'Retourne le nombre d\'actions par type (created/updated/deleted).')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function stats(): array
    {
        return [
            'status' => 'ok',
            'data' => AuditLog::stats(),
        ];
    }

    #[ApiDescription('Activite recente', 'Retourne les dernieres entrees du journal d\'audit.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function recentActivity(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);

        return [
            'status' => 'ok',
            'data' => AuditLog::recentActivity($limit),
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/AuditController.php';
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $dir = "{$this->appDir}/Routes";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/audit.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes audit.php existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\AuditController;
use App\Middleware\Auth;

// ─── Admin only : Audit Trail (SOC 2 compliance) ──────────────
$router->group([
    'prefix' => '/audit',
    'description' => 'Audit Trail — SOC 2 compliance',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('/stats', [AuditController::class, 'stats']);
    $router->get('/recent', [AuditController::class, 'recentActivity']);
    $router->get('/entity/{type}/{entityId}', [AuditController::class, 'forEntity']);
    $router->get('/users/{userId}', [AuditController::class, 'byUser']);
    $router->get('/{id}', [AuditController::class, 'show']);
    $router->get('', [AuditController::class, 'index']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/audit.php';
    }

    // ─── SQL Migrations ────────────────────────────────────────

    private function pgsqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS audit_logs ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' auditable_type VARCHAR(255) NOT NULL,'
            . ' auditable_id BIGINT NOT NULL,'
            . ' action VARCHAR(20) NOT NULL,'
            . ' old_values JSONB DEFAULT \'{}\'::jsonb,'
            . ' new_values JSONB DEFAULT \'{}\'::jsonb,'
            . ' user_id BIGINT,'
            . ' ip_address VARCHAR(45),'
            . ' request_id VARCHAR(32),'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_auditable ON audit_logs (auditable_type, auditable_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs (user_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at)';
    }

    private function mysqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS audit_logs ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' auditable_type VARCHAR(255) NOT NULL,'
            . ' auditable_id BIGINT UNSIGNED NOT NULL,'
            . ' action VARCHAR(20) NOT NULL,'
            . ' old_values JSON DEFAULT NULL,'
            . ' new_values JSON DEFAULT NULL,'
            . ' user_id BIGINT UNSIGNED DEFAULT NULL,'
            . ' ip_address VARCHAR(45) DEFAULT NULL,'
            . ' request_id VARCHAR(32) DEFAULT NULL,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' INDEX idx_audit_logs_auditable (auditable_type, auditable_id),'
            . ' INDEX idx_audit_logs_user (user_id),'
            . ' INDEX idx_audit_logs_created (created_at)'
            . ')';
    }

    private function sqliteUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS audit_logs ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' auditable_type TEXT NOT NULL,'
            . ' auditable_id INTEGER NOT NULL,'
            . ' action TEXT NOT NULL,'
            . ' old_values TEXT DEFAULT \'{}\','
            . ' new_values TEXT DEFAULT \'{}\','
            . ' user_id INTEGER,'
            . ' ip_address TEXT,'
            . ' request_id TEXT,'
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_auditable ON audit_logs (auditable_type, auditable_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs (user_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at)';
    }
}
