<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookDeliveryItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('ID du webhook')]
        public int $webhook_id,
        #[Description('Event declenche')]
        public string $event,
        #[Description('URL de destination')]
        public string $url,
        #[Description('Statut de la livraison')]
        public string $status,
        #[Description('Code HTTP retourne')]
        public int $http_status,
        #[Description('Numero de tentative')]
        public int $attempt,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}