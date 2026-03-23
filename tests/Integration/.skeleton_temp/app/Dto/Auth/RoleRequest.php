<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class RoleRequest
{
    public function __construct(
        #[Required]
        #[Description('Role name')]
        public string $name,
        #[Description('Role description')]
        public ?string $description = null,
    ) {
    }
}