<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class PermissionResponse
{
    public function __construct(
        #[Description('Permission ID')]
        public int $id,
        #[Description('Permission name')]
        public string $name,
        #[Description('Permission description')]
        public ?string $description = null,
    ) {
    }
}