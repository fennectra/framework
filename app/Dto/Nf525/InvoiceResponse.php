<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;

readonly class InvoiceResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Facture')]
        public ?InvoiceItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}