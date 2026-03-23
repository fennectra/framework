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