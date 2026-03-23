<?php

namespace App\Dto\Email;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class EmailTemplateStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Nom unique du template')]
        public string $name,
        #[Description('Locale du template (fr, en, etc.)')]
        public string $locale = 'fr',
        #[Required]
        #[Description('Sujet de l\'email (supporte {{variables}})')]
        public string $subject = '',
        #[Required]
        #[Description('Corps de l\'email en HTML (supporte {{variables}})')]
        public string $body = '',
    ) {
    }
}