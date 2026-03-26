<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:nf525', 'Generate NF525 module: migration + Models + DTOs + Controller + Routes')]
class MakeNf525Command implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   NF525 — Generation du module conformite fiscale ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migration
        $this->createMigration();

        // 2. Models
        $this->createInvoiceModel();
        $this->createInvoiceLineModel();
        $this->createNf525ClosingModel();
        $this->createNf525JournalModel();

        // 3. DTOs
        $this->createDto('InvoiceItem', $this->dtoInvoiceItem());
        $this->createDto('InvoiceStoreRequest', $this->dtoInvoiceStoreRequest());
        $this->createDto('InvoiceResponse', $this->dtoInvoiceResponse());
        $this->createDto('InvoiceListRequest', $this->dtoInvoiceListRequest());
        $this->createDto('InvoiceLineItem', $this->dtoInvoiceLineItem());
        $this->createDto('Nf525ClosingItem', $this->dtoNf525ClosingItem());
        $this->createDto('Nf525StatsResponse', $this->dtoNf525StatsResponse());

        // 4. Controller
        $this->createController();

        // 5. Routes
        $this->createRoutes();

        // Resume
        echo "\n\033[1;32m  ✓ Module NF525 genere avec succes\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mRoutes API (admin only — conformite fiscale) :\033[0m\n";
        echo "    GET    /nf525/invoices              Lister les factures\n";
        echo "    GET    /nf525/invoices/{id}         Detail d'une facture avec lignes\n";
        echo "    POST   /nf525/invoices              Creer une facture\n";
        echo "    POST   /nf525/invoices/{id}/credit  Creer un avoir\n";
        echo "    GET    /nf525/closings               Lister les clotures\n";
        echo "    POST   /nf525/closings               Declencher une cloture\n";
        echo "    GET    /nf525/verify                 Verifier la chaine de hash\n";
        echo "    GET    /nf525/fec/export             Exporter le FEC\n";
        echo "    GET    /nf525/journal                Journal des evenements\n";
        echo "    GET    /nf525/stats                  Statistiques NF525\n";
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
            if (str_contains($file, 'create_nf525_tables')) {
                echo "  \033[33m⚠ Migration deja existante, ignoree\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_nf525_tables";

        $driver = Env::get('DB_DRIVER', 'pgsql');
        $content = $this->buildMigration($driver);

        file_put_contents("{$dir}/{$filename}.php", $content);
        $this->created[] = "database/migrations/{$filename}.php";
    }

    // ─── Models ────────────────────────────────────────────────

    private function createInvoiceModel(): void
    {
        $file = "{$this->appDir}/Models/Invoice.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model Invoice existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Nf525;
use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Nf525\HasNf525;
use Fennec\Core\Relations\HasMany;

#[Table('invoices')]
#[Nf525(prefix: 'FA')]
class Invoice extends Model
{
    use HasNf525;

    /** @var array<string, string> */
    protected static array $casts = [
        'total_ht' => 'float',
        'tva' => 'float',
        'total_ttc' => 'float',
        'is_credit' => 'bool',
        'credit_of' => 'int',
    ];

    /**
     * Lignes de la facture.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    /**
     * Statistiques globales des factures.
     */
    public static function stats(): array
    {
        $stmt = DB::raw(
            'SELECT
                COUNT(*) as total_invoices,
                COUNT(CASE WHEN is_credit = FALSE OR is_credit IS NULL THEN 1 END) as invoices_count,
                COUNT(CASE WHEN is_credit = TRUE THEN 1 END) as credit_notes_count,
                COALESCE(SUM(CASE WHEN is_credit = FALSE OR is_credit IS NULL THEN total_ht ELSE 0 END), 0) as total_ht,
                COALESCE(SUM(CASE WHEN is_credit = FALSE OR is_credit IS NULL THEN total_ttc ELSE 0 END), 0) as total_ttc,
                COALESCE(SUM(CASE WHEN is_credit = TRUE THEN total_ht ELSE 0 END), 0) as credit_ht,
                COALESCE(SUM(CASE WHEN is_credit = TRUE THEN total_ttc ELSE 0 END), 0) as credit_ttc
             FROM invoices'
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Par mois (12 derniers mois)
        $stmtMonths = DB::raw(
            'SELECT
                TO_CHAR(created_at, \'YYYY-MM\') as month,
                COUNT(*) as count,
                COALESCE(SUM(total_ht), 0) as total_ht,
                COALESCE(SUM(total_ttc), 0) as total_ttc
             FROM invoices
             WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL \'12 months\'
             GROUP BY TO_CHAR(created_at, \'YYYY-MM\')
             ORDER BY month DESC'
        );

        $months = $stmtMonths->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'invoices_count' => (int) ($row['invoices_count'] ?? 0),
            'credit_notes_count' => (int) ($row['credit_notes_count'] ?? 0),
            'total_ht' => (float) ($row['total_ht'] ?? 0),
            'total_ttc' => (float) ($row['total_ttc'] ?? 0),
            'credit_ht' => (float) ($row['credit_ht'] ?? 0),
            'credit_ttc' => (float) ($row['credit_ttc'] ?? 0),
            'by_month' => $months,
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/Invoice.php';
    }

    private function createInvoiceLineModel(): void
    {
        $file = "{$this->appDir}/Models/InvoiceLine.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model InvoiceLine existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;
use Fennec\Core\Relations\BelongsTo;

#[Table('invoice_lines')]
class InvoiceLine extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'invoice_id' => 'int',
        'quantity' => 'float',
        'unit_price' => 'float',
        'tva_rate' => 'float',
        'total_ht' => 'float',
        'total_ttc' => 'float',
    ];

    /**
     * La facture parente.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/InvoiceLine.php';
    }

    private function createNf525ClosingModel(): void
    {
        $file = "{$this->appDir}/Models/Nf525Closing.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model Nf525Closing existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;

#[Table('nf525_closings')]
class Nf525Closing extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
        'cumulative_total' => 'float',
        'document_count' => 'int',
    ];
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/Nf525Closing.php';
    }

    private function createNf525JournalModel(): void
    {
        $file = "{$this->appDir}/Models/Nf525Journal.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model Nf525Journal existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\Model;

#[Table('nf525_journal')]
class Nf525Journal extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'entity_id' => 'int',
        'user_id' => 'int',
        'details' => 'json',
    ];
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/Nf525Journal.php';
    }

    // ─── DTOs ──────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Nf525";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} existe deja, ignore\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Nf525/{$name}.php";
    }

    private function dtoInvoiceItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class InvoiceItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Numero de facture (sequentiel NF525)')]
        public string $number,
        #[Description('Nom du client')]
        public string $client_name,
        #[Description('Adresse du client')]
        public ?string $client_address = null,
        #[Description('SIRET du client')]
        public ?string $client_siret = null,
        #[Description('Total hors taxes')]
        public float $total_ht = 0,
        #[Description('Montant TVA')]
        public float $tva = 0,
        #[Description('Total toutes taxes comprises')]
        public float $total_ttc = 0,
        #[Description('Est un avoir')]
        public bool $is_credit = false,
        #[Description('ID de la facture creditee')]
        public ?int $credit_of = null,
        #[Description('Motif de l\'avoir')]
        public ?string $credit_reason = null,
        #[Description('Hash SHA-256 (chaine NF525)')]
        public ?string $hash = null,
        #[Description('Hash precedent (chaine NF525)')]
        public ?string $previous_hash = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
        #[Description('Date de mise a jour')]
        public ?string $updated_at = null,
        #[Description('Lignes de la facture')]
        public ?array $lines = null,
    ) {
    }
}
PHP;
    }

    private function dtoInvoiceStoreRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class InvoiceStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Nom du client')]
        public string $client_name = '',
        #[Required]
        #[Description('Adresse du client')]
        public string $client_address = '',
        #[Description('SIRET du client')]
        public ?string $client_siret = null,
        #[Required]
        #[Description('Lignes de facture (description, quantity, unit_price, tva_rate)')]
        public array $lines = [],
    ) {
    }
}
PHP;
    }

    private function dtoInvoiceResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class InvoiceResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Facture')]
        public ?InvoiceItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}
PHP;
    }

    private function dtoInvoiceListRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class InvoiceListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer les avoirs uniquement')]
        public ?bool $is_credit = null,
        #[Description('Date de debut (YYYY-MM-DD)')]
        public ?string $date_from = null,
        #[Description('Date de fin (YYYY-MM-DD)')]
        public ?string $date_to = null,
    ) {
    }
}
PHP;
    }

    private function dtoInvoiceLineItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class InvoiceLineItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('ID de la facture')]
        public int $invoice_id,
        #[Description('Description de la ligne')]
        public string $description,
        #[Description('Quantite')]
        public float $quantity = 0,
        #[Description('Prix unitaire HT')]
        public float $unit_price = 0,
        #[Description('Taux de TVA (%)')]
        public float $tva_rate = 0,
        #[Description('Total HT de la ligne')]
        public float $total_ht = 0,
        #[Description('Total TTC de la ligne')]
        public float $total_ttc = 0,
    ) {
    }
}
PHP;
    }

    private function dtoNf525ClosingItem(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class Nf525ClosingItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Type de cloture (daily, monthly, annual)')]
        public string $type,
        #[Description('Debut de la periode')]
        public string $period_start,
        #[Description('Fin de la periode')]
        public string $period_end,
        #[Description('Total HT')]
        public float $total_ht = 0,
        #[Description('Total TVA')]
        public float $total_tva = 0,
        #[Description('Total TTC')]
        public float $total_ttc = 0,
        #[Description('Cumul general')]
        public float $cumulative_total = 0,
        #[Description('Nombre de documents')]
        public int $document_count = 0,
        #[Description('Hash HMAC de la cloture')]
        public ?string $hash = null,
        #[Description('Hash precedent')]
        public ?string $previous_hash = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoNf525StatsResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class Nf525StatsResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Nombre de factures')]
        public ?int $invoices_count = null,
        #[Description('Nombre d\'avoirs')]
        public ?int $credit_notes_count = null,
        #[Description('Total HT')]
        public ?float $total_ht = null,
        #[Description('Total TTC')]
        public ?float $total_ttc = null,
        #[Description('Dernieres clotures')]
        public ?array $closings = null,
        #[Description('Validite de la chaine de hash')]
        public ?bool $chain_valid = null,
    ) {
    }
}
PHP;
    }

    // ─── Controller ────────────────────────────────────────────

    private function createController(): void
    {
        $file = "{$this->appDir}/Controllers/Nf525Controller.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller Nf525Controller existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Nf525\InvoiceItem;
use App\Dto\Nf525\InvoiceListRequest;
use App\Dto\Nf525\InvoiceResponse;
use App\Dto\Nf525\InvoiceStoreRequest;
use App\Dto\Nf525\Nf525StatsResponse;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Nf525Closing;
use App\Models\Nf525Journal;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Nf525\ClosingService;
use Fennec\Core\Nf525\FecExporter;
use Fennec\Core\Nf525\HashChainVerifier;

class Nf525Controller
{
    // ─── Factures ─────────────────────────────────────────────

    #[ApiDescription('Lister les factures', 'Retourne la liste paginee des factures NF525.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(InvoiceListRequest $request): array
    {
        $query = Invoice::query();

        if ($request->is_credit !== null) {
            $query->where('is_credit', '=', $request->is_credit);
        }

        if ($request->date_from !== null) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to !== null) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        return Invoice::paginate($request->limit, $request->page);
    }

    #[ApiDescription('Detail d\'une facture', 'Retourne une facture avec ses lignes.')]
    #[ApiStatus(200, 'Facture trouvee')]
    #[ApiStatus(404, 'Facture non trouvee')]
    public function show(string $id): InvoiceResponse
    {
        $invoice = Invoice::findOrFail((int) $id);
        $lines = InvoiceLine::where('invoice_id', '=', (int) $id)
            ->orderBy('id')
            ->get();

        $data = $invoice->toArray();
        $data['lines'] = array_map(fn ($line) => $line->toArray(), $lines);

        return new InvoiceResponse(
            status: 'ok',
            data: new InvoiceItem(...$data),
        );
    }

    #[ApiDescription('Creer une facture', 'Cree une facture avec ses lignes. Numerotation et hash automatiques (NF525).')]
    #[ApiStatus(201, 'Facture creee')]
    #[ApiStatus(422, 'Donnees invalides')]
    public function store(InvoiceStoreRequest $input): InvoiceResponse
    {
        if (empty($input->lines)) {
            throw new HttpException(422, 'La facture doit contenir au moins une ligne');
        }

        // Calculer les totaux des lignes
        $totalHt = 0;
        $totalTva = 0;
        $linesData = [];

        foreach ($input->lines as $line) {
            $lineHt = round((float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0), 2);
            $lineTvaRate = (float) ($line['tva_rate'] ?? 20);
            $lineTtc = round($lineHt * (1 + $lineTvaRate / 100), 2);

            $totalHt += $lineHt;
            $totalTva += round($lineTtc - $lineHt, 2);

            $linesData[] = [
                'description' => $line['description'] ?? '',
                'quantity' => (float) ($line['quantity'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'tva_rate' => $lineTvaRate,
                'total_ht' => $lineHt,
                'total_ttc' => $lineTtc,
            ];
        }

        $totalTtc = round($totalHt + $totalTva, 2);

        // Creer la facture (HasNf525 gere le numero et le hash)
        $invoice = Invoice::create([
            'client_name' => $input->client_name,
            'client_address' => $input->client_address,
            'client_siret' => $input->client_siret,
            'total_ht' => $totalHt,
            'tva' => $totalTva,
            'total_ttc' => $totalTtc,
        ]);

        // Creer les lignes
        foreach ($linesData as $lineData) {
            InvoiceLine::create(array_merge($lineData, [
                'invoice_id' => (int) $invoice->getAttribute('id'),
            ]));
        }

        // Recharger avec les lignes
        $data = $invoice->toArray();
        $lines = InvoiceLine::where('invoice_id', '=', (int) $invoice->getAttribute('id'))->get();
        $data['lines'] = array_map(fn ($line) => $line->toArray(), $lines);

        return new InvoiceResponse(
            status: 'ok',
            data: new InvoiceItem(...$data),
            message: 'Facture ' . $invoice->getAttribute('number') . ' creee',
        );
    }

    #[ApiDescription('Creer un avoir', 'Cree un avoir (credit note) pour une facture existante.')]
    #[ApiStatus(201, 'Avoir cree')]
    #[ApiStatus(404, 'Facture non trouvee')]
    public function creditNote(string $id): InvoiceResponse
    {
        $invoice = Invoice::findOrFail((int) $id);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = $body['reason'] ?? 'Avoir';

        $credit = $invoice->createCredit($reason);

        $data = $credit->toArray();
        $lines = InvoiceLine::where('invoice_id', '=', (int) $credit->getAttribute('id'))->get();
        $data['lines'] = array_map(fn ($line) => $line->toArray(), $lines);

        return new InvoiceResponse(
            status: 'ok',
            data: new InvoiceItem(...$data),
            message: 'Avoir ' . $credit->getAttribute('number') . ' cree pour la facture ' . $invoice->getAttribute('number'),
        );
    }

    // ─── Clotures ─────────────────────────────────────────────

    #[ApiDescription('Lister les clotures', 'Retourne la liste paginee des clotures NF525.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function closings(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);

        return Nf525Closing::paginate($limit, $page);
    }

    #[ApiDescription('Declencher une cloture', 'Cree une cloture journaliere, mensuelle ou annuelle.')]
    #[ApiStatus(201, 'Cloture creee')]
    #[ApiStatus(422, 'Parametres invalides')]
    public function createClosing(): array
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $body['type'] ?? null;
        $period = $body['period'] ?? null;

        if (!$type || !in_array($type, ['daily', 'monthly', 'annual'], true)) {
            throw new HttpException(422, 'Type de cloture invalide (daily, monthly, annual)');
        }

        if (!$period) {
            throw new HttpException(422, 'Periode requise (YYYY-MM-DD pour daily, YYYY-MM pour monthly, YYYY pour annual)');
        }

        $closingService = new ClosingService();

        $closing = match ($type) {
            'daily' => $closingService->daily($period),
            'monthly' => $closingService->monthly($period),
            'annual' => $closingService->annual($period),
        };

        return [
            'status' => 'ok',
            'data' => $closing->toArray(),
            'message' => 'Cloture ' . $type . ' creee pour la periode ' . $period,
        ];
    }

    // ─── Verification et export ───────────────────────────────

    #[ApiDescription('Verifier la chaine de hash', 'Verifie l\'integrite de la chaine de hash NF525.')]
    #[ApiStatus(200, 'Verification effectuee')]
    public function verifyChain(): array
    {
        $table = $_GET['table'] ?? 'invoices';
        $result = HashChainVerifier::verify($table);

        return [
            'status' => 'ok',
            'data' => $result,
        ];
    }

    #[ApiDescription('Exporter le FEC', 'Genere et telecharge le Fichier des Ecritures Comptables.')]
    #[ApiStatus(200, 'Fichier genere')]
    public function exportFec(): void
    {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $exporter = new FecExporter();
        $content = $exporter->export($year);

        $filename = 'FEC_' . $year . '_' . date('Ymd_His') . '.txt';

        header('Content-Type: text/tab-separated-values; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo $content;
        exit;
    }

    // ─── Journal ──────────────────────────────────────────────

    #[ApiDescription('Journal des evenements NF525', 'Retourne la liste paginee des evenements du journal NF525.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function journal(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);

        return Nf525Journal::paginate($limit, $page);
    }

    // ─── Statistiques ─────────────────────────────────────────

    #[ApiDescription('Statistiques NF525', 'Dashboard avec totaux, compteurs et validation de la chaine.')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function stats(): Nf525StatsResponse
    {
        $invoiceStats = Invoice::stats();

        $closings = Nf525Closing::query()
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();

        $chainResult = HashChainVerifier::verify('invoices');

        return new Nf525StatsResponse(
            status: 'ok',
            invoices_count: $invoiceStats['invoices_count'],
            credit_notes_count: $invoiceStats['credit_notes_count'],
            total_ht: $invoiceStats['total_ht'],
            total_ttc: $invoiceStats['total_ttc'],
            closings: array_map(fn ($c) => $c->toArray(), $closings),
            chain_valid: $chainResult['valid'] ?? false,
        );
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/Nf525Controller.php';
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $file = "{$this->appDir}/Routes/nf525.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes nf525.php existe deja, ignore\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\Nf525Controller;
use App\Middleware\Auth;

// ─── NF525 — Conformite fiscale (admin only) ──────────────────
$router->group([
    'prefix' => '/nf525',
    'description' => 'NF525 — Conformite fiscale (factures, clotures, journal)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    // Factures
    $router->get('/invoices', [Nf525Controller::class, 'index']);
    $router->get('/invoices/{id}', [Nf525Controller::class, 'show']);
    $router->post('/invoices', [Nf525Controller::class, 'store']);
    $router->post('/invoices/{id}/credit', [Nf525Controller::class, 'creditNote']);

    // Clotures
    $router->get('/closings', [Nf525Controller::class, 'closings']);
    $router->post('/closings', [Nf525Controller::class, 'createClosing']);

    // Verification et export
    $router->get('/verify', [Nf525Controller::class, 'verifyChain']);
    $router->get('/fec/export', [Nf525Controller::class, 'exportFec']);

    // Journal
    $router->get('/journal', [Nf525Controller::class, 'journal']);

    // Statistiques
    $router->get('/stats', [Nf525Controller::class, 'stats']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/nf525.php';
    }

    // ─── SQL Migrations ────────────────────────────────────────

    private function buildMigration(string $driver): string
    {
        $up = match ($driver) {
            'mysql' => $this->mysqlUp(),
            'sqlite' => $this->sqliteUp(),
            default => $this->pgsqlUp(),
        };

        $down = 'DROP TABLE IF EXISTS nf525_journal; DROP TABLE IF EXISTS nf525_closings; DROP TABLE IF EXISTS invoice_lines; DROP TABLE IF EXISTS invoices';

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

    private function pgsqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS invoices ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' number VARCHAR(30) NOT NULL UNIQUE,'
            . ' client_name VARCHAR(255) NOT NULL,'
            . ' client_address TEXT,'
            . ' client_siret VARCHAR(14),'
            . ' total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' tva DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' is_credit BOOLEAN DEFAULT FALSE,'
            . ' credit_of BIGINT,'
            . ' credit_reason TEXT,'
            . ' hash VARCHAR(64) NOT NULL DEFAULT \'\','
            . ' previous_hash VARCHAR(64) NOT NULL DEFAULT \'0\','
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_invoices_number ON invoices (number);'
            . ' CREATE INDEX IF NOT EXISTS idx_invoices_created ON invoices (created_at);'
            . ' CREATE TABLE IF NOT EXISTS invoice_lines ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' invoice_id BIGINT NOT NULL REFERENCES invoices(id),'
            . ' description TEXT NOT NULL,'
            . ' quantity DECIMAL(10,3) NOT NULL DEFAULT 1,'
            . ' unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20,'
            . ' total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_invoice_lines_invoice ON invoice_lines (invoice_id);'
            . ' CREATE TABLE IF NOT EXISTS nf525_closings ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' type VARCHAR(10) NOT NULL,'
            . ' period_start DATE NOT NULL,'
            . ' period_end DATE NOT NULL,'
            . ' total_ht DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' total_tva DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' cumulative_total DECIMAL(16,2) NOT NULL DEFAULT 0,'
            . ' document_count INT NOT NULL DEFAULT 0,'
            . ' hash VARCHAR(64) NOT NULL,'
            . ' previous_hash VARCHAR(64) NOT NULL DEFAULT \'0\','
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE(type, period_start, period_end)'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS nf525_journal ('
            . ' id BIGSERIAL PRIMARY KEY,'
            . ' event VARCHAR(50) NOT NULL,'
            . ' entity_type VARCHAR(100),'
            . ' entity_id BIGINT,'
            . ' details JSONB DEFAULT \'{}\'::jsonb,'
            . ' user_id BIGINT,'
            . ' ip_address VARCHAR(45),'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE INDEX IF NOT EXISTS idx_nf525_journal_event ON nf525_journal (event);'
            . ' CREATE INDEX IF NOT EXISTS idx_nf525_journal_created ON nf525_journal (created_at)';
    }

    private function mysqlUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS invoices ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' number VARCHAR(30) NOT NULL UNIQUE,'
            . ' client_name VARCHAR(255) NOT NULL,'
            . ' client_address TEXT,'
            . ' client_siret VARCHAR(14),'
            . ' total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' tva DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' is_credit TINYINT(1) DEFAULT 0,'
            . ' credit_of BIGINT UNSIGNED DEFAULT NULL,'
            . ' credit_reason TEXT,'
            . ' hash VARCHAR(64) NOT NULL DEFAULT \'\','
            . ' previous_hash VARCHAR(64) NOT NULL DEFAULT \'0\','
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS invoice_lines ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' invoice_id BIGINT UNSIGNED NOT NULL,'
            . ' description TEXT NOT NULL,'
            . ' quantity DECIMAL(10,3) NOT NULL DEFAULT 1,'
            . ' unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20,'
            . ' total_ht DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' INDEX idx_invoice_lines_invoice (invoice_id)'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS nf525_closings ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' type VARCHAR(10) NOT NULL,'
            . ' period_start DATE NOT NULL,'
            . ' period_end DATE NOT NULL,'
            . ' total_ht DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' total_tva DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' total_ttc DECIMAL(14,2) NOT NULL DEFAULT 0,'
            . ' cumulative_total DECIMAL(16,2) NOT NULL DEFAULT 0,'
            . ' document_count INT NOT NULL DEFAULT 0,'
            . ' hash VARCHAR(64) NOT NULL,'
            . ' previous_hash VARCHAR(64) NOT NULL DEFAULT \'0\','
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE KEY uk_closing (type, period_start, period_end)'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS nf525_journal ('
            . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . ' event VARCHAR(50) NOT NULL,'
            . ' entity_type VARCHAR(100),'
            . ' entity_id BIGINT UNSIGNED,'
            . ' details JSON DEFAULT NULL,'
            . ' user_id BIGINT UNSIGNED DEFAULT NULL,'
            . ' ip_address VARCHAR(45) DEFAULT NULL,'
            . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . ' INDEX idx_nf525_journal_event (event),'
            . ' INDEX idx_nf525_journal_created (created_at)'
            . ')';
    }

    private function sqliteUp(): string
    {
        return 'CREATE TABLE IF NOT EXISTS invoices ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' number TEXT NOT NULL UNIQUE,'
            . ' client_name TEXT NOT NULL,'
            . ' client_address TEXT,'
            . ' client_siret TEXT,'
            . ' total_ht REAL NOT NULL DEFAULT 0,'
            . ' tva REAL NOT NULL DEFAULT 0,'
            . ' total_ttc REAL NOT NULL DEFAULT 0,'
            . ' is_credit INTEGER DEFAULT 0,'
            . ' credit_of INTEGER,'
            . ' credit_reason TEXT,'
            . ' hash TEXT NOT NULL DEFAULT \'\','
            . ' previous_hash TEXT NOT NULL DEFAULT \'0\','
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
            . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS invoice_lines ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' invoice_id INTEGER NOT NULL REFERENCES invoices(id),'
            . ' description TEXT NOT NULL,'
            . ' quantity REAL NOT NULL DEFAULT 1,'
            . ' unit_price REAL NOT NULL DEFAULT 0,'
            . ' tva_rate REAL NOT NULL DEFAULT 20,'
            . ' total_ht REAL NOT NULL DEFAULT 0,'
            . ' total_ttc REAL NOT NULL DEFAULT 0,'
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS nf525_closings ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' type TEXT NOT NULL,'
            . ' period_start TEXT NOT NULL,'
            . ' period_end TEXT NOT NULL,'
            . ' total_ht REAL NOT NULL DEFAULT 0,'
            . ' total_tva REAL NOT NULL DEFAULT 0,'
            . ' total_ttc REAL NOT NULL DEFAULT 0,'
            . ' cumulative_total REAL NOT NULL DEFAULT 0,'
            . ' document_count INTEGER NOT NULL DEFAULT 0,'
            . ' hash TEXT NOT NULL,'
            . ' previous_hash TEXT NOT NULL DEFAULT \'0\','
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
            . ' UNIQUE(type, period_start, period_end)'
            . ');'
            . ' CREATE TABLE IF NOT EXISTS nf525_journal ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' event TEXT NOT NULL,'
            . ' entity_type TEXT,'
            . ' entity_id INTEGER,'
            . ' details TEXT DEFAULT \'{}\','
            . ' user_id INTEGER,'
            . ' ip_address TEXT,'
            . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ')';
    }
}
