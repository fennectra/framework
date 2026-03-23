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