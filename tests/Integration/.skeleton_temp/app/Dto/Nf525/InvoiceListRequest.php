<?php

namespace App\Dto\Nf525;

use Fennec\Attributes\Description;
use Fennec\Attributes\Min;

readonly class InvoiceListRequest
{
    public function __construct(
        #[Description('Nombre d\'elements par page')]
        #[Min(1)]
        public int $limit = 20,
        #[Description('Numero de page')]
        #[Min(1)]
        public int $page = 1,
        #[Description('Filtrer les avoirs uniquement')]
        public ?bool $is_credit = null,
        #[Description('Date de debut (YYYY-MM-DD)')]
        public ?string $date_from = null,
        #[Description('Date de fin (YYYY-MM-DD)')]
        public ?string $date_to = null,
    ) {
    }
}