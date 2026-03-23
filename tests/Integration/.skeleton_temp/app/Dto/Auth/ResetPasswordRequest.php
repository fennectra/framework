<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\MinLength;
use Fennec\Attributes\Required;

readonly class ResetPasswordRequest
{
    public function __construct(
        #[Required]
        #[Description('Password reset token')]
        public string $token,
        #[Required]
        #[MinLength(8)]
        #[Description('New password (min 8 characters)')]
        public string $password,
    ) {
    }
}