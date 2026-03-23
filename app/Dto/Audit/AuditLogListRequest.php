<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class AuditLogListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par action (created/updated/deleted)')]
        public ?string $action = null,
        #[Description('Filtrer par type d\'entite')]
        public ?string $auditable_type = null,
        #[Description('Filtrer par utilisateur')]
        public ?int $user_id = null,
        #[Description('Date de debut (YYYY-MM-DD)')]
        public ?string $date_from = null,
        #[Description('Date de fin (YYYY-MM-DD)')]
        public ?string $date_to = null,
    ) {
    }
}