<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Nf525\ClosingService;
use Fennec\Core\Nf525\FecExporter;
use Fennec\Core\Nf525\HashChainVerifier;
use Fennec\Core\Response;

class UiNf525Controller
{
    use UiHelper;

    public function invoices(): void
    {
        try {
            Response::json($this->paginate('invoices'));
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function closings(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM nf525_closings ORDER BY id DESC')->fetchAll();
            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function verify(): void
    {
        try {
            $result = HashChainVerifier::verify('invoices');
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json([
                'valid' => false,
                'total' => 0,
                'errors' => [['id' => 0, 'error' => $e->getMessage()]],
            ]);
        }
    }

    public function close(): void
    {
        $body = $this->body();
        $type = $body['type'] ?? 'daily';
        $period = $body['period'] ?? '';

        if (!$period) {
            Response::json(['error' => 'Period is required'], 422);

            return;
        }

        try {
            $service = new ClosingService();
            $result = match ($type) {
                'daily' => $service->closeDaily($period),
                'monthly' => $service->closeMonthly($period),
                'annual' => $service->closeAnnual($period),
                default => throw new \InvalidArgumentException('Invalid closing type'),
            };

            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function fec(): void
    {
        $year = $this->queryString('year', date('Y'));

        try {
            $exporter = new FecExporter();
            $path = $exporter->exportToFile($year);

            Response::json(['url' => '/storage/' . basename($path), 'path' => $path]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function journal(): void
    {
        try {
            Response::json($this->paginate('nf525_journal'));
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function stats(): void
    {
        try {
            $invoiceCount = DB::raw('SELECT COUNT(*) as cnt FROM invoices')->fetchAll()[0]['cnt'] ?? 0;
            $closingCount = DB::raw('SELECT COUNT(*) as cnt FROM nf525_closings')->fetchAll()[0]['cnt'] ?? 0;
            $lastClosing = DB::raw('SELECT * FROM nf525_closings ORDER BY id DESC LIMIT 1')->fetchAll()[0] ?? null;

            Response::json([
                'invoices' => (int) $invoiceCount,
                'closings' => (int) $closingCount,
                'lastClosing' => $lastClosing,
            ]);
        } catch (\Throwable) {
            Response::json(['invoices' => 0, 'closings' => 0, 'lastClosing' => null]);
        }
    }
}
