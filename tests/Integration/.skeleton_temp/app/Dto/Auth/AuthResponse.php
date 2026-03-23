<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class AuthResponse
{
    public function __construct(
        #[Description('JWT access token')]
        public string $access_token,
        #[Description('Refresh token')]
        public ?string $refresh_token = null,
        #[Description('Token expiration time in seconds')]
        public ?int $expires_in = null,
        #[Description('Authenticated user')]
        public ?UserResponse $user = null,
    ) {
    }
}