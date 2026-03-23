<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\MaxLength;
use Fennec\Attributes\Required;

readonly class ConsentObjectStoreRequest
{
    public function __construct(
        #[Required]
        #[MaxLength(255)]
        #[Description('Nom du document legal')]
        public string $object_name = '',
        #[Required]
        #[Description('Contenu HTML du document')]
        public string $object_content = '',
        #[Required]
        #[MaxLength(50)]
        #[Description('Cle unique (cgu, legal, pcpd)')]
        public string $key = '',
        #[Description('Consentement obligatoire')]
        public bool $is_required = true,
    ) {
    }
}