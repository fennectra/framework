<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class RgpdStatsResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Taux de conformite')]
        public ?array $compliance = null,
        #[Description('Statistiques par document')]
        public ?array $documents = null,
        #[Description('Utilisateurs non conformes')]
        public ?array $non_compliant_users = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}