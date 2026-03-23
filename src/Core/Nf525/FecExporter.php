<?php

namespace Fennec\Core\Nf525;

use Fennec\Core\DB;
use Fennec\Core\Env;

/**
 * Export FEC (Fichier des Ecritures Comptables) — format fiscal francais.
 *
 * Genere un fichier CSV/TSV conforme aux exigences de l'administration fiscale.
 * Format: JournalCode|EcritureDate|CompteNum|CompteLib|Debit|Credit|...
 */
class FecExporter
{
    private string $table;
    private string $connection;

    /** @var string[] Colonnes FEC normalisees */
    private const FEC_HEADERS = [
        'JournalCode',
        'JournalLib',
        'EcritureNum',
        'EcritureDate',
        'CompteNum',
        'CompteLib',
        'PieceRef',
        'PieceDate',
        'EcritureLib',
        'Debit',
        'Credit',
        'Montantdevise',
        'Idevise',
    ];

    public function __construct(
        string $table = 'invoices',
        string $connection = 'default',
    ) {
        $this->table = $table;
        $this->connection = $connection;
    }

    /**
     * Exporte les ecritures d'une annee au format FEC.
     *
     * @return string Contenu du fichier FEC (TSV)
     */
    public function export(string $year): string
    {
        $start = $year . '-01-01 00:00:00';
        $end = (string) ((int) $year + 1) . '-01-01 00:00:00';

        $stmt = DB::raw(
            "SELECT * FROM {$this->table} WHERE created_at >= :start AND created_at < :end ORDER BY id ASC",
            ['start' => $start, 'end' => $end],
            $this->connection
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lines = [];
        $lines[] = implode("\t", self::FEC_HEADERS);

        foreach ($rows as $row) {
            $lines[] = $this->formatRow($row);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Exporte vers un fichier.
     */
    public function exportToFile(string $year, ?string $path = null): string
    {
        $siren = Env::get('NF525_SIREN', '000000000');
        $path ??= "FEC{$siren}{$year}1231.txt";

        $content = $this->export($year);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Retourne le nombre d'ecritures pour une annee.
     */
    public function count(string $year): int
    {
        $start = $year . '-01-01 00:00:00';
        $end = (string) ((int) $year + 1) . '-01-01 00:00:00';

        $stmt = DB::raw(
            "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= :start AND created_at < :end",
            ['start' => $start, 'end' => $end],
            $this->connection
        );

        return (int) $stmt->fetchColumn();
    }

    private function formatRow(array $row): string
    {
        $isCredit = !empty($row['is_credit']);
        $totalTtc = abs((float) ($row['total_ttc'] ?? 0));
        $date = isset($row['created_at']) ? date('Ymd', strtotime($row['created_at'])) : '';

        $fields = [
            'VE',                                          // JournalCode (Ventes)
            'Journal des Ventes',                          // JournalLib
            $row['number'] ?? '',                          // EcritureNum
            $date,                                         // EcritureDate
            '411000',                                      // CompteNum (Clients)
            $row['client_name'] ?? 'Client',               // CompteLib
            $row['number'] ?? '',                          // PieceRef
            $date,                                         // PieceDate
            $this->buildLabel($row),                       // EcritureLib
            $isCredit ? '0.00' : number_format($totalTtc, 2, '.', ''),  // Debit
            $isCredit ? number_format($totalTtc, 2, '.', '') : '0.00',  // Credit
            number_format($totalTtc, 2, '.', ''),          // Montantdevise
            'EUR',                                         // Idevise
        ];

        return implode("\t", $fields);
    }

    private function buildLabel(array $row): string
    {
        $number = $row['number'] ?? '';
        $client = $row['client_name'] ?? '';

        if (!empty($row['is_credit'])) {
            $reason = $row['credit_reason'] ?? 'Avoir';

            return "Avoir {$number} - {$client} - {$reason}";
        }

        return "Facture {$number} - {$client}";
    }
}
