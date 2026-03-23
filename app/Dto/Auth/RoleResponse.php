<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class RoleResponse
{
    public function __construct(
        #[Description('Role ID')]
        public int $id,
        #[Description('Role name')]
        public string $name,
        #[Description('Role description')]
        public ?string $description = null,
        #[Description('Assigned permissions')]
        public array $permissions = [],
    ) {
    }
}