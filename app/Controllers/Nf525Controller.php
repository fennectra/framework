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