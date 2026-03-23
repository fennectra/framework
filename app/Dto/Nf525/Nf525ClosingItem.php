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