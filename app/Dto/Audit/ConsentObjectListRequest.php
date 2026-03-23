<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class ConsentObjectListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer par cle (cgu, legal, pcpd)')]
        public ?string $key = null,
    ) {
    }
}