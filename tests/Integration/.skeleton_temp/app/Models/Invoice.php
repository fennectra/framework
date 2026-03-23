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
             WHERE created_at >= NOW() - INTERVAL \'12 months\'
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