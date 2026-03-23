<?php

namespace App\Dto\Webhook;

use Fennec\Attributes\Description;

readonly class WebhookResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Webhook')]
        public ?WebhookItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}