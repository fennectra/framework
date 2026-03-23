<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du webhook')]
        public string $name,
        #[Description('URL de destination')]
        public string $url,
        #[Description('Secret HMAC-SHA256')]
        public string $secret,
        #[Description('Events auxquels le webhook est abonne')]
        public array $events,
        #[Description('Webhook actif')]
        public bool $is_active,
        #[Description('Description du webhook')]
        public ?string $description = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
        #[Description('Date de mise a jour')]
        public ?string $updated_at = null,
    ) {
    }
}