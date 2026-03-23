<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class UserResponse
{
    public function __construct(
        #[Description('User ID')]
        public int $id,
        #[Description('Full name')]
        public string $name,
        #[Description('Email address')]
        public string $email,
        #[Description('Assigned roles')]
        public array $roles = [],
        #[Description('Whether the account is active')]
        public bool $is_active = true,
        #[Description('Account creation date')]
        public ?string $created_at = null,
    ) {
    }
}