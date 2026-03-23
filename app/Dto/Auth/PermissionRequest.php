<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class PermissionRequest
{
    public function __construct(
        #[Required]
        #[Description('Permission name')]
        public string $name,
        #[Description('Permission description')]
        public ?string $description = null,
    ) {
    }
}