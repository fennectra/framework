<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organization_members')]
class OrganizationMember extends Model
{
    /**
     * Get the organization this member belongs to.
     */
    public function organization(): ?Organization
    {
        return Organization::find($this->organization_id);
    }

    /**
     * Get the user associated with this membership.
     */
    public function user(): ?Model
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $this->user_id]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? User::hydrate($row) : null;
    }
}