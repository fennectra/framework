<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organization_invitations')]
class OrganizationInvitation extends Model
{
    /**
     * Get the organization this invitation belongs to.
     */
    public function organization(): ?Organization
    {
        return Organization::find($this->organization_id);
    }

    /**
     * Find an invitation by its token.
     */
    public static function findByToken(string $token): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM organization_invitations WHERE token = :token LIMIT 1',
            ['token' => $token]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}