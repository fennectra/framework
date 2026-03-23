<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class InvoiceStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Nom du client')]
        public string $client_name = '',
        #[Required]
        #[Description('Adresse du client')]
        public string $client_address = '',
        #[Description('SIRET du client')]
        public ?string $client_siret = null,
        #[Required]
        #[Description('Lignes de facture (description, quantity, unit_price, tva_rate)')]
        public array $lines = [],
    ) {
    }
}