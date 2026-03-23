<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;
use Fennec\Attributes\MaxLength;
use Fennec\Attributes\Required;

readonly class WebhookStoreRequest
{
    public function __construct(
        #[Required]
        #[MaxLength(255)]
        #[Description('Nom du webhook')]
        public string $name = '',
        #[Required]
        #[MaxLength(2048)]
        #[Description('URL de destination')]
        public string $url = '',
        #[Description('Secret HMAC-SHA256 (genere automatiquement si absent)')]
        public ?string $secret = null,
        #[Description('Events auxquels le webhook est abonne')]
        public array $events = [],
        #[Description('Webhook actif')]
        public bool $is_active = true,
        #[Description('Description du webhook')]
        public ?string $description = null,
    ) {
    }
}