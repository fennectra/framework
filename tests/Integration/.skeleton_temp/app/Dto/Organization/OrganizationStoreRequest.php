<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class OrganizationStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Organization name')]
        public string $name,
        #[Description('Organization description')]
        public ?string $description = null,
    ) {
    }
}