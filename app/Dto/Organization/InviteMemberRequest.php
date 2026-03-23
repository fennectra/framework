<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class InviteMemberRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address of the person to invite')]
        public string $email,
        #[Description('Role to assign (owner, admin, member)')]
        public string $role = 'member',
    ) {
    }
}