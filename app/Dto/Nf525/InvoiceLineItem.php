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