<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;

readonly class OrganizationResponse
{
    public function __construct(
        #[Description('Unique identifier')]
        public int $id,
        #[Description('Organization name')]
        public string $name,
        #[Description('URL-friendly slug')]
        public string $slug,
        #[Description('Owner information')]
        public mixed $owner = null,
        #[Description('Number of members')]
        public int $members_count = 0,
        #[Description('Creation date')]
        public ?string $created_at = null,
    ) {
    }
}