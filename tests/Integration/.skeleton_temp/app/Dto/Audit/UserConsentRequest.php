<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class UserConsentRequest
{
    public function __construct(
        #[Required]
        #[Description('ID du document legal')]
        public int $consent_object_id = 0,
        #[Required]
        #[Description('Acceptation du consentement')]
        public bool $consent_status = false,
        #[Description('Moyen de consentement (web, api, email, paper)')]
        public string $consent_way = 'web',
    ) {
    }
}