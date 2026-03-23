<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class ConsentObjectItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Nom du document')]
        public string $object_name,
        #[Description('Contenu HTML')]
        public string $object_content,
        #[Description('Numero de version')]
        public int $object_version,
        #[Description('Version precedente (ID)')]
        public ?int $object_previous_version = null,
        #[Description('Cle unique (cgu, legal, pcpd)')]
        public ?string $key = null,
        #[Description('Consentement obligatoire')]
        public ?bool $is_required = true,
        #[Description('Date de creation')]
        public ?string $created_at = null,
        #[Description('Date de mise a jour')]
        public ?string $updated_at = null,
    ) {
    }
}