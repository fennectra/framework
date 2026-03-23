<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class LoginRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address')]
        public string $email,
        #[Required]
        #[Description('Password')]
        public string $password,
    ) {
    }
}