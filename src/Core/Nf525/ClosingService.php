<?php

namespace Fennec\Core\Nf525;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Security\SecurityLogger;

/**
 * Service de cloture periodique NF525.
 *
 * Genere des clotures journalieres, mensuelles ou annuelles
 * avec hash de verification et grand total cumule.
 */
class ClosingService
{
    private string $table;
    private string $closingTable;
    private string $connection;

    public function __construct(
        string $table = 'invoices',
        string $closingTable = 'nf525_closings',
        string $connection = 'default',
    ) {
        $this->table = $table;
        $this->closingTable = $closingTable;
        $this->connection = $connection;
    }

    /**
     * Cloture journaliere.
     */
    public function closeDaily(string $date): array
    {
        return $this->close('daily', $date, $date);
    }

    /**
     * Cloture mensuelle (format: 2026-03).
     */
    public function closeMonthly(string $yearMonth): array
    {
        $start = $yearMonth . '-01';
        $end = date('Y-m-t', strtotime($start));

        return $this->close('monthly', $start, $end);
    }

    /**
     * Cloture annuelle (format: 2026).
     */
    public function closeAnnual(string $year): array
    {
        $start = $year . '-01-01';
        $end = $year . '-12-31';

        return $this->close('annual', $start, $end);
    }

    /**
     * Verifie si une periode est deja cloturee.
     */
    public function isClosed(string $type, string $periodStart, string $periodEnd): bool
    {
        $stmt = DB::raw(
            "SELECT COUNT(*) FROM {$this->closingTable} WHERE type = :type AND period_start = :start AND period_end = :end",
            ['type' => $type, 'start' => $periodStart, 'end' => $periodEnd],
            $this->connection
        );

        return (int) $stmt->fetchColumn() > 0;
    }

    private function close(string $type, string $periodStart, string $periodEnd): array
    {
        // Verifier si deja cloturee
        if ($this->isClosed($type, $periodStart, $periodEnd)) {
            throw new \RuntimeException("Periode deja cloturee: {$type} {$periodStart} - {$periodEnd}");
        }

        // Calculer les totaux de la periode
        $totals = $this->computeTotals($periodStart, $periodEnd);

        // Recuperer le hash de la derniere cloture
        $previousHash = $this->getLastClosingHash();

        // Calculer le hash de cette cloture
        $payload = json_encode([
            'type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_ht' => $totals['total_ht'],
            'total_tva' => $totals['total_tva'],
            'total_ttc' => $totals['total_ttc'],
            'document_count' => $totals['document_count'],
            'previous_hash' => $previousHash,
        ], JSON_UNESCAPED_UNICODE);

        $key = Env::get('SECRET_KEY', 'fennec-nf525');
        $hash = hash_hmac('sha256', $payload, $key);

        $now = date('Y-m-d H:i:s');

        // Grand total cumule
        $cumulativeTotal = $this->getCumulativeTotal() + $totals['total_ttc'];

        DB::raw(
            "INSERT INTO {$this->closingTable} (type, period_start, period_end, total_ht, total_tva, total_ttc, cumulative_total, document_count, hash, previous_hash, created_at) VALUES (:type, :start, :end, :ht, :tva, :ttc, :cumul, :count, :hash, :prev, :now)",
            [
                'type' => $type,
                'start' => $periodStart,
                'end' => $periodEnd,
                'ht' => $totals['total_ht'],
                'tva' => $totals['total_tva'],
                'ttc' => $totals['total_ttc'],
                'cumul' => $cumulativeTotal,
                'count' => $totals['document_count'],
                'hash' => $hash,
                'prev' => $previousHash,
                'now' => $now,
            ],
            $this->connection
        );

        SecurityLogger::track('nf525.closing', [
            'type' => $type,
            'period' => "{$periodStart} - {$periodEnd}",
            'document_count' => $totals['document_count'],
            'total_ttc' => $totals['total_ttc'],
        ]);

        return [
            'type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'totals' => $totals,
            'cumulative_total' => $cumulativeTotal,
            'hash' => $hash,
        ];
    }

    private function computeTotals(string $start, string $end): array
    {
        $stmt = DB::raw(
            "SELECT COALESCE(SUM(total_ht), 0) as total_ht, COALESCE(SUM(tva), 0) as total_tva, COALESCE(SUM(total_ttc), 0) as total_ttc, COUNT(*) as document_count FROM {$this->table} WHERE created_at >= :start AND created_at < :end_next",
            [
                'start' => $start . ' 00:00:00',
                'end_next' => date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00',
            ],
            $this->connection
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_ht' => round((float) $row['total_ht'], 2),
            'total_tva' => round((float) $row['total_tva'], 2),
            'total_ttc' => round((float) $row['total_ttc'], 2),
            'document_count' => (int) $row['document_count'],
        ];
    }

    private function getLastClosingHash(): string
    {
        $stmt = DB::raw(
            "SELECT hash FROM {$this->closingTable} ORDER BY id DESC LIMIT 1",
            [],
            $this->connection
        );

        $hash = $stmt->fetchColumn();

        return $hash !== false ? (string) $hash : '0';
    }

    private function getCumulativeTotal(): float
    {
        $stmt = DB::raw(
            "SELECT cumulative_total FROM {$this->closingTable} ORDER BY id DESC LIMIT 1",
            [],
            $this->connection
        );

        $total = $stmt->fetchColumn();

        return $total !== false ? (float) $total : 0.0;
    }
}
