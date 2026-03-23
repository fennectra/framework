<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\MinLength;
use Fennec\Attributes\Required;

readonly class RegisterRequest
{
    public function __construct(
        #[Required]
        #[Description('Full name of the user')]
        public string $name,
        #[Required]
        #[Email]
        #[Description('Email address')]
        public string $email,
        #[Required]
        #[MinLength(8)]
        #[Description('Password (min 8 characters)')]
        public string $password,
    ) {
    }
}