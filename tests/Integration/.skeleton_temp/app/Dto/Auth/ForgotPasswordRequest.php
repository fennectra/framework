<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class ForgotPasswordRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address of the account')]
        public string $email,
    ) {
    }
}