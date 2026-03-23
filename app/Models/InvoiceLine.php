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