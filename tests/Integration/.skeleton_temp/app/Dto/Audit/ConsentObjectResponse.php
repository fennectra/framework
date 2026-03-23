<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class ConsentObjectResponse
{
    public function __construct(
        #[Description('Statut de la requete')]
        public string $status,
        #[Description('Document legal')]
        public ?ConsentObjectItem $data = null,
        #[Description('Message informatif')]
        public ?string $message = null,
    ) {
    }
}