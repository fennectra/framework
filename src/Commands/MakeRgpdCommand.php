<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:rgpd', 'Generate RGPD: migration + Models + DTOs + Controller + Routes')]
class MakeRgpdCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   RGPD — Generation du module consentement   ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migration
        $this->createMigration();

        // 2. Models
        $this->createConsentObjectModel();
        $this->createUserConsentModel();

        // 3. DTOs
        $this->createDto('ConsentObjectItem', $this->dtoConsentObjectItem());
        $this->createDto('ConsentObjectStoreRequest', $this->dtoConsentObjectStoreRequest());
        $this->createDto('ConsentObjectResponse', $this->dtoConsentObjectResponse());
        $this->createDto('ConsentObjectListRequest', $this->dtoConsentObjectListRequest());
        $this->createDto('UserConsentRequest', $this->dtoUserConsentRequest());
        $this->createDto('RgpdStatsResponse', $this->dtoRgpdStatsResponse());

        // 4. Controller
        $this->createController();

        // 5. Routes
        $this->createRoutes();

        // Resume
        echo "\n\033[1;32m  ✓ Module RGPD genere avec succes\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes API :\033[0m\n";
        echo "    \033[90mPublic :\033[0m\n";
        echo "      GET    /consent/documents/{key}/latest      Derniere version d'un document\n";
        echo "    \033[90mUtilisateur authentifie :\033[0m\n";
        echo "      POST   /consent/me                          Donner son consentement\n";
        echo "      GET    /consent/me                          Mon statut de consentement\n";
        echo "      DELETE /consent/me                          Retirer mes consentements\n";
        echo "    \033[90mAdmin :\033[0m\n";
        echo "      GET    /consent/documents                   Lister les documents\n";
        echo "      GET    /consent/documents/{id}              Detail d'un document\n";
        echo "      POST   /consent/documents                   Creer une nouvelle version\n";
        echo "    \033[90mDPO / Admin :\033[0m\n";
        echo "      GET    /consent/dpo/dashboard               Dashboard RGPD complet\n";
        echo "      GET    /consent/dpo/stats                   Stats par document\n";
        echo "      GET    /consent/dpo/compliance              Taux de conformite\n";
        echo "      GET    /consent/dpo/non-compliant           Utilisateurs non conformes\n";
        echo "      GET    /consent/dpo/users/{id}/history      Historique d'un utilisateur\n";
        echo "      GET    /consent/dpo/users/{id}/export       Export portabilite RGPD\n";
        echo "      DELETE /consent/dpo/users/{id}/consents     Droit a l'oubli\n";
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
            if (str_contains($file, 'create_rgpd_tables')) {
                echo "  \033[33m⚠ Migration deja existante, ignoree\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_rgpd_tables";

        $driver = Env::get('DB_DRIVER', 'pgsql');
        $up = match ($driver) {
            'mysql' => $this->mysqlUp(),
            'sqlite' => $this->sqliteUp(),
            default => $this->pgsqlUp(),
        };

        $down = 'DROP TABLE IF EXISTS user_consents; DROP TABLE IF EXISTS consent_objects';

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

    private function createConsentObjectModel(): void
    {
        $file = "{$this->appDir}/Models/ConsentObject.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model ConsentObject existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;
use Fennec\Core\Relations\HasMany;

#[Table('consent_objects')]
class ConsentObject extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'object_version' => 'int',
        'object_previous_version' => 'int',
        'is_required' => 'bool',
    ];

    /**
     * Les consentements utilisateurs lies a ce document.
     */
    public function userConsents(): HasMany
    {
        return $this->hasMany(UserConsent::class, 'consent_object_id');
    }

    /**
     * Retourne la derniere version active pour une cle donnee.
     */
    public static function latestByKey(string $key): ?self
    {
        $results = static::where('key', '=', $key)
            ->orderBy('object_version', 'DESC')
            ->limit(1)
            ->get();

        return $results[0] ?? null;
    }

    /**
     * Retourne toutes les cles distinctes avec leur derniere version.
     */
    public static function allLatest(): array
    {
        $all = static::query()->orderBy('key')->orderBy('object_version', 'DESC')->get();
        $latest = [];
        foreach ($all as $item) {
            $key = $item->getAttribute('key');
            if (!isset($latest[$key])) {
                $latest[$key] = $item;
            }
        }

        return array_values($latest);
    }

    /**
     * Cree une nouvelle version d'un document existant.
     */
    public static function createNewVersion(string $key, string $name, string $content, bool $isRequired = true): self
    {
        $current = static::latestByKey($key);
        $newVersion = $current ? $current->getAttribute('object_version') + 1 : 1;
        $previousId = $current ? $current->getAttribute('id') : null;

        return static::create([
            'object_name' => $name,
            'object_content' => $content,
            'object_version' => $newVersion,
            'object_previous_version' => $previousId,
            'key' => $key,
            'is_required' => $isRequired,
        ]);
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/ConsentObject.php';
    }

    private function createUserConsentModel(): void
    {
        $file = "{$this->appDir}/Models/UserConsent.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model UserConsent existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\BelongsTo;

#[Table('user_consents')]
class UserConsent extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
        'consent_object_id' => 'int',
        'consent_status' => 'bool',
        'object_version' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function consentObject(): BelongsTo
    {
        return $this->belongsTo(ConsentObject::class, 'consent_object_id');
    }

    /**
     * Enregistre ou met a jour le consentement d'un utilisateur.
     */
    public static function recordConsent(
        int $userId,
        int $consentObjectId,
        bool $status,
        int $objectVersion,
        string $way = 'web'
    ): self {
        $existing = static::where('user_id', '=', $userId)
            ->where('consent_object_id', '=', $consentObjectId)
            ->first();

        if ($existing) {
            $existing->fill([
                'consent_status' => $status,
                'object_version' => $objectVersion,
                'consent_way' => $way,
            ])->save();

            return $existing;
        }

        return static::create([
            'user_id' => $userId,
            'consent_object_id' => $consentObjectId,
            'consent_status' => $status,
            'object_version' => $objectVersion,
            'consent_way' => $way,
        ]);
    }

    /**
     * Verifie si un utilisateur a accepte tous les documents requis (derniere version).
     */
    public static function hasAcceptedAll(int $userId): bool
    {
        $required = ConsentObject::allLatest();
        foreach ($required as $doc) {
            if (!$doc->getAttribute('is_required')) {
                continue;
            }
            $consent = static::where('user_id', '=', $userId)
                ->where('consent_object_id', '=', $doc->getAttribute('id'))
                ->first();
            if (!$consent || !$consent->getAttribute('consent_status')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Statistiques de consentement par document (DPO).
     */
    public static function statsByDocument(): array
    {
        $stmt = DB::raw(
            'SELECT co.id, co.object_name, co.key, co.object_version, co.is_required,
                    COUNT(uc.id) as total_responses,
                    COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) as accepted,
                    COUNT(CASE WHEN uc.consent_status = FALSE THEN 1 END) as refused
             FROM consent_objects co
             LEFT JOIN user_consents uc ON uc.consent_object_id = co.id
             GROUP BY co.id, co.object_name, co.key, co.object_version, co.is_required
             ORDER BY co.key, co.object_version DESC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Taux de conformite global (DPO).
     */
    public static function complianceRate(): array
    {
        $stmt = DB::raw(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE is_active = TRUE) as total_active_users,
                COUNT(DISTINCT compliant.user_id) as compliant_users
             FROM (
                SELECT uc.user_id
                FROM user_consents uc
                JOIN consent_objects co ON co.id = uc.consent_object_id
                JOIN users u ON u.id = uc.user_id AND u.is_active = TRUE
                WHERE co.is_required = TRUE
                  AND co.id IN (
                    SELECT co2.id FROM consent_objects co2
                    WHERE co2.object_version = (
                        SELECT MAX(co3.object_version) FROM consent_objects co3 WHERE co3.key = co2.key
                    )
                  )
                  AND uc.consent_status = TRUE
                GROUP BY uc.user_id
                HAVING COUNT(DISTINCT co.key) = (
                    SELECT COUNT(DISTINCT co4.key)
                    FROM consent_objects co4
                    WHERE co4.is_required = TRUE
                      AND co4.object_version = (
                          SELECT MAX(co5.object_version) FROM consent_objects co5 WHERE co5.key = co4.key
                      )
                )
             ) compliant'
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int) ($row['total_active_users'] ?? 0);
        $compliant = (int) ($row['compliant_users'] ?? 0);

        return [
            'total_active_users' => $total,
            'compliant_users' => $compliant,
            'non_compliant_users' => $total - $compliant,
            'compliance_rate' => $total > 0 ? round($compliant / $total * 100, 2) : 0,
        ];
    }

    /**
     * Utilisateurs non conformes (DPO).
     */
    public static function nonCompliantUsers(int $limit = 50, int $offset = 0): array
    {
        $stmt = DB::raw(
            'SELECT u.id, u.email, u.created_at,
                    COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) as accepted_count,
                    COUNT(CASE WHEN uc.consent_status = FALSE OR uc.id IS NULL THEN 1 END) as missing_count
             FROM users u
             LEFT JOIN user_consents uc ON uc.user_id = u.id
                 AND uc.consent_object_id IN (
                     SELECT co.id FROM consent_objects co
                     WHERE co.is_required = TRUE
                       AND co.object_version = (
                           SELECT MAX(co2.object_version) FROM consent_objects co2 WHERE co2.key = co.key
                       )
                 )
             WHERE u.is_active = TRUE
             GROUP BY u.id, u.email, u.created_at
             HAVING COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) < (
                 SELECT COUNT(DISTINCT co3.key)
                 FROM consent_objects co3
                 WHERE co3.is_required = TRUE
                   AND co3.object_version = (
                       SELECT MAX(co4.object_version) FROM consent_objects co4 WHERE co4.key = co3.key
                   )
             )
             ORDER BY u.email
             LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Historique de consentement d'un utilisateur (droit d'acces RGPD).
     */
    public static function userHistory(int $userId): array
    {
        $stmt = DB::raw(
            'SELECT uc.id, co.object_name, co.key, uc.object_version,
                    uc.consent_status, uc.consent_way, uc.created_at, uc.updated_at
             FROM user_consents uc
             JOIN consent_objects co ON co.id = uc.consent_object_id
             WHERE uc.user_id = :user_id
             ORDER BY uc.updated_at DESC',
            ['user_id' => $userId]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export des consentements (droit a la portabilite RGPD).
     */
    public static function exportForUser(int $userId): array
    {
        return [
            'user_id' => $userId,
            'exported_at' => date('c'),
            'consents' => self::userHistory($userId),
        ];
    }

    /**
     * Retrait de tous les consentements (droit a l'oubli RGPD).
     */
    public static function withdrawAll(int $userId): int
    {
        $consents = static::where('user_id', '=', $userId)->get();
        $count = 0;
        foreach ($consents as $consent) {
            $consent->fill(['consent_status' => false, 'consent_way' => 'withdrawal'])->save();
            $count++;
        }

        return $count;
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/UserConsent.php';
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

    private function dtoConsentObjectItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class ConsentObjectItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du document')]
        public string $object_name,
        #[Description('Contenu HTML')]
        public string $object_content,
        #[Description('Numero de version')]
        public int $object_version,
        #[Description('Version precedente (ID)')]
        public ?int $object_previous_version = null,
        #[Description('Cle unique (cgu, legal, pcpd)')]
        public ?string $key = null,
        #[Description('Consentement obligatoire')]
        public ?bool $is_required = true,
        #[Description('Date de creation')]
        public ?string $created_at = null,
        #[Description('Date de mise a jour')]
        public ?string $updated_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoConsentObjectStoreRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\MaxLength;
use Fennec\Attributes\Required;

readonly class ConsentObjectStoreRequest
{
    public function __construct(
        #[Required]
        #[MaxLength(255)]
        #[Description('Nom du document legal')]
        public string $object_name = '',
        #[Required]
        #[Description('Contenu HTML du document')]
        public string $object_content = '',
        #[Required]
        #[MaxLength(50)]
        #[Description('Cle unique (cgu, legal, pcpd)')]
        public string $key = '',
        #[Description('Consentement obligatoire')]
        public bool $is_required = true,
    ) {
    }
}
PHP;
    }

    private function dtoConsentObjectResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class ConsentObjectResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Document legal')]
        public ?ConsentObjectItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}
PHP;
    }

    private function dtoConsentObjectListRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class ConsentObjectListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par cle (cgu, legal, pcpd)')]
        public ?string $key = null,
    ) {
    }
}
PHP;
    }

    private function dtoUserConsentRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class UserConsentRequest
{
    public function __construct(
        #[Required]
        #[Description('ID du document legal')]
        public int $consent_object_id = 0,
        #[Required]
        #[Description('Acceptation du consentement')]
        public bool $consent_status = false,
        #[Description('Moyen de consentement (web, api, email, paper)')]
        public string $consent_way = 'web',
    ) {
    }
}
PHP;
    }

    private function dtoRgpdStatsResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class RgpdStatsResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Taux de conformite')]
        public ?array $compliance = null,
        #[Description('Statistiques par document')]
        public ?array $documents = null,
        #[Description('Utilisateurs non conformes')]
        public ?array $non_compliant_users = null,
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
        $file = "{$this->appDir}/Controllers/ConsentController.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller ConsentController existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Audit\ConsentObjectItem;
use App\Dto\Audit\ConsentObjectListRequest;
use App\Dto\Audit\ConsentObjectResponse;
use App\Dto\Audit\ConsentObjectStoreRequest;
use App\Dto\Audit\RgpdStatsResponse;
use App\Dto\Audit\UserConsentRequest;
use App\Middleware\Auth;
use App\Models\ConsentObject;
use App\Models\UserConsent;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;

class ConsentController
{
    // ─── Documents legaux (CRUD admin) ─────────────────────────

    #[ApiDescription('Lister les documents legaux', 'Retourne la liste paginee des documents RGPD.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(ConsentObjectListRequest $request): array
    {
        if ($request->key) {
            $items = ConsentObject::where('key', '=', $request->key)
                ->orderBy('object_version', 'DESC')
                ->get();

            return [
                'data' => array_map(fn ($item) => $item->toArray(), $items),
                'meta' => ['total' => count($items)],
            ];
        }

        return ConsentObject::paginate($request->limit, $request->page);
    }

    #[ApiDescription('Afficher un document legal')]
    #[ApiStatus(200, 'Document trouve')]
    #[ApiStatus(404, 'Document non trouve')]
    public function show(string $id): ConsentObjectResponse
    {
        $item = ConsentObject::findOrFail((int) $id);

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Derniere version d\'un document par cle', 'Retourne la derniere version active (cgu, legal, pcpd).')]
    #[ApiStatus(200, 'Document trouve')]
    #[ApiStatus(404, 'Document non trouve')]
    public function latest(string $key): ConsentObjectResponse
    {
        $item = ConsentObject::latestByKey($key);

        if (!$item) {
            throw new HttpException(404, 'Document non trouve pour la cle : ' . $key);
        }

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Creer une nouvelle version d\'un document legal')]
    #[ApiStatus(201, 'Document cree')]
    public function store(ConsentObjectStoreRequest $input): ConsentObjectResponse
    {
        $item = ConsentObject::createNewVersion(
            $input->key,
            $input->object_name,
            $input->object_content,
            $input->is_required,
        );

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
            message: 'Document legal cree (version ' . $item->getAttribute('object_version') . ')',
        );
    }

    // ─── Consentement utilisateur ──────────────────────────────

    #[ApiDescription('Donner son consentement', 'L\'utilisateur connecte accepte ou refuse un document legal.')]
    #[ApiStatus(200, 'Consentement enregistre')]
    #[ApiStatus(404, 'Document non trouve')]
    public function consent(UserConsentRequest $input): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $doc = ConsentObject::findOrFail($input->consent_object_id);

        $consent = UserConsent::recordConsent(
            userId: (int) $user['id'],
            consentObjectId: $input->consent_object_id,
            status: $input->consent_status,
            objectVersion: (int) $doc->getAttribute('object_version'),
            way: $input->consent_way,
        );

        return [
            'status' => 'ok',
            'message' => $input->consent_status ? 'Consentement accepte' : 'Consentement refuse',
            'data' => $consent->toArray(),
        ];
    }

    #[ApiDescription('Mon statut de consentement', 'Retourne le statut de consentement de l\'utilisateur connecte.')]
    #[ApiStatus(200, 'Statut retourne')]
    public function myConsents(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $userId = (int) $user['id'];

        return [
            'status' => 'ok',
            'data' => [
                'is_compliant' => UserConsent::hasAcceptedAll($userId),
                'consents' => UserConsent::userHistory($userId),
            ],
        ];
    }

    #[ApiDescription('Retirer tous mes consentements', 'Droit d\'opposition RGPD.')]
    #[ApiStatus(200, 'Consentements retires')]
    public function withdrawMyConsents(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $count = UserConsent::withdrawAll((int) $user['id']);

        return [
            'status' => 'ok',
            'message' => $count . ' consentement(s) retire(s)',
        ];
    }

    // ─── DPO / Admin : statistiques et conformite ──────────────

    #[ApiDescription('Dashboard RGPD (DPO)', 'Tableau de bord avec taux de conformite, stats par document, utilisateurs non conformes.')]
    #[ApiStatus(200, 'Dashboard retourne')]
    public function dashboard(): RgpdStatsResponse
    {
        return new RgpdStatsResponse(
            status: 'ok',
            compliance: UserConsent::complianceRate(),
            documents: UserConsent::statsByDocument(),
            non_compliant_users: UserConsent::nonCompliantUsers(10),
        );
    }

    #[ApiDescription('Statistiques par document')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function stats(): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::statsByDocument(),
        ];
    }

    #[ApiDescription('Taux de conformite RGPD')]
    #[ApiStatus(200, 'Taux retourne')]
    public function complianceRate(): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::complianceRate(),
        ];
    }

    #[ApiDescription('Utilisateurs non conformes')]
    #[ApiStatus(200, 'Liste retournee')]
    public function nonCompliant(): array
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        return [
            'status' => 'ok',
            'data' => UserConsent::nonCompliantUsers($limit, $offset),
        ];
    }

    #[ApiDescription('Historique de consentement d\'un utilisateur', 'Droit d\'acces RGPD.')]
    #[ApiStatus(200, 'Historique retourne')]
    public function userHistory(string $userId): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::userHistory((int) $userId),
        ];
    }

    #[ApiDescription('Exporter les consentements d\'un utilisateur', 'Droit a la portabilite RGPD.')]
    #[ApiStatus(200, 'Export retourne')]
    public function exportUser(string $userId): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::exportForUser((int) $userId),
        ];
    }

    #[ApiDescription('Retirer les consentements d\'un utilisateur', 'Droit a l\'oubli RGPD.')]
    #[ApiStatus(200, 'Consentements retires')]
    public function withdrawUser(string $userId): array
    {
        $count = UserConsent::withdrawAll((int) $userId);

        return [
            'status' => 'ok',
            'message' => $count . ' consentement(s) retire(s) pour l\'utilisateur #' . $userId,
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/ConsentController.php';
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $file = "{$this->appDir}/Routes/consent.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes consent.php existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\ConsentController;
use App\Middleware\Auth;

// ─── Public : consultation des documents legaux ───────────────
$router->group([
    'prefix' => '/consent',
    'description' => 'RGPD — Documents legaux (public)',
], function ($router) {
    $router->get('/documents/{key}/latest', [ConsentController::class, 'latest']);
});

// ─── Utilisateur authentifie : donner/consulter son consentement ─
$router->group([
    'prefix' => '/consent',
    'description' => 'RGPD — Consentement utilisateur',
    'middleware' => [[Auth::class, ['user', 'cip', 'manager', 'admin', 'freelance', 'france_travail', 'editor']]],
], function ($router) {
    $router->post('/me', [ConsentController::class, 'consent']);
    $router->get('/me', [ConsentController::class, 'myConsents']);
    $router->delete('/me', [ConsentController::class, 'withdrawMyConsents']);
});

// ─── Admin : CRUD documents legaux ────────────────────────────
$router->group([
    'prefix' => '/consent/documents',
    'description' => 'RGPD — Gestion des documents legaux (admin)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('', [ConsentController::class, 'index']);
    $router->get('/{id}', [ConsentController::class, 'show']);
    $router->post('', [ConsentController::class, 'store']);
});

// ─── DPO / Admin : statistiques et droits RGPD ───────────────
$router->group([
    'prefix' => '/consent/dpo',
    'description' => 'RGPD — Dashboard DPO, conformite et droits des personnes',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('/dashboard', [ConsentController::class, 'dashboard']);
    $router->get('/stats', [ConsentController::class, 'stats']);
    $router->get('/compliance', [ConsentController::class, 'complianceRate']);
    $router->get('/non-compliant', [ConsentController::class, 'nonCompliant']);
    $router->get('/users/{userId}/history', [ConsentController::class, 'userHistory']);
    $router->get('/users/{userId}/export', [ConsentController::class, 'exportUser']);
    $router->delete('/users/{userId}/consents', [ConsentController::class, 'withdrawUser']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/consent.php';
    }

    // ─── SQL Migrations ────────────────────────────────────────

    private function pgsqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS consent_objects ('
            . ' id SERIAL PRIMARY KEY,'
            . ' object_name VARCHAR(255) NOT NULL,'
            . ' object_content TEXT NOT NULL,'
            . ' object_version INTEGER NOT NULL DEFAULT 1,'
            . ' object_previous_version INTEGER DEFAULT NULL REFERENCES consent_objects(id),'
            . ' key VARCHAR(50) DEFAULT NULL,'
            . ' is_required BOOLEAN DEFAULT TRUE,'
            . ' created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_consent_objects_key ON consent_objects (key);'
            . ' CREATE INDEX IF NOT EXISTS idx_consent_objects_version ON consent_objects (key, object_version);'
            . ' CREATE TABLE IF NOT EXISTS user_consents ('
            . ' id SERIAL PRIMARY KEY,'
            . ' user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
            . ' consent_object_id INTEGER NOT NULL REFERENCES consent_objects(id) ON DELETE CASCADE,'
            . ' consent_status BOOLEAN NOT NULL DEFAULT FALSE,'
            . ' consent_way VARCHAR(50) NOT NULL DEFAULT \'web\','
            . ' object_version INTEGER NOT NULL,'
            . ' created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_user_consents_user ON user_consents (user_id);'
            . ' CREATE INDEX IF NOT EXISTS idx_user_consents_object ON user_consents (consent_object_id);'
            . ' CREATE UNIQUE INDEX IF NOT EXISTS idx_user_consents_unique ON user_consents (user_id, consent_object_id)';
    }

    private function mysqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS consent_objects ('
            . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' object_name VARCHAR(255) NOT NULL,'
            . ' object_content TEXT NOT NULL,'
            . ' object_version INT UNSIGNED NOT NULL DEFAULT 1,'
            . ' object_previous_version INT UNSIGNED DEFAULT NULL,'
            . ' `key` VARCHAR(50) DEFAULT NULL,'
            . ' is_required TINYINT(1) DEFAULT 1,'
            . ' created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
            . ' INDEX idx_consent_objects_key (`key`),'
            . ' INDEX idx_consent_objects_version (`key`, object_version),'
            . ' CONSTRAINT fk_co_previous FOREIGN KEY (object_previous_version) REFERENCES consent_objects(id)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            . ' CREATE TABLE IF NOT EXISTS user_consents ('
            . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' user_id INT UNSIGNED NOT NULL,'
            . ' consent_object_id INT UNSIGNED NOT NULL,'
            . ' consent_status TINYINT(1) NOT NULL DEFAULT 0,'
            . ' consent_way VARCHAR(50) NOT NULL DEFAULT \'web\','
            . ' object_version INT UNSIGNED NOT NULL,'
            . ' created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
            . ' INDEX idx_user_consents_user (user_id),'
            . ' INDEX idx_user_consents_object (consent_object_id),'
            . ' UNIQUE INDEX idx_user_consents_unique (user_id, consent_object_id),'
            . ' CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,'
            . ' CONSTRAINT fk_uc_consent FOREIGN KEY (consent_object_id) REFERENCES consent_objects(id) ON DELETE CASCADE'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    private function sqliteUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS consent_objects ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' object_name TEXT NOT NULL,'
            . ' object_content TEXT NOT NULL,'
            . ' object_version INTEGER NOT NULL DEFAULT 1,'
            . ' object_previous_version INTEGER DEFAULT NULL REFERENCES consent_objects(id),'
            . ' key TEXT DEFAULT NULL,'
            . ' is_required INTEGER DEFAULT 1,'
            . ' created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_consent_objects_key ON consent_objects (key);'
            . ' CREATE INDEX IF NOT EXISTS idx_consent_objects_version ON consent_objects (key, object_version);'
            . ' CREATE TABLE IF NOT EXISTS user_consents ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
            . ' consent_object_id INTEGER NOT NULL REFERENCES consent_objects(id) ON DELETE CASCADE,'
            . ' consent_status INTEGER NOT NULL DEFAULT 0,'
            . ' consent_way TEXT NOT NULL DEFAULT \'web\','
            . ' object_version INTEGER NOT NULL,'
            . ' created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE UNIQUE INDEX IF NOT EXISTS idx_user_consents_unique ON user_consents (user_id, consent_object_id)';
    }
}
