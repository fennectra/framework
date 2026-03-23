<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class AuditLogResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Entree d\'audit')]
        public ?AuditLogItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}