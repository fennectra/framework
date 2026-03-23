<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class WebhookListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par statut actif')]
        public ?bool $is_active = null,
    ) {
    }
}