<?php

namespace App\Dto\Email;

use Fennec\Attributes\Description;

readonly class EmailTemplateItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du template')]
        public string $name,
        #[Description('Locale du template')]
        public string $locale,
        #[Description('Sujet de l\'email')]
        public string $subject,
        #[Description('Corps de l\'email')]
        public string $body,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}