<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;

readonly class OrganizationMemberResponse
{
    public function __construct(
        #[Description('Membership identifier')]
        public int $id,
        #[Description('User information')]
        public mixed $user = null,
        #[Description('Member role in the organization')]
        public string $role = 'member',
        #[Description('Date when the member joined')]
        public ?string $joined_at = null,
    ) {
    }
}