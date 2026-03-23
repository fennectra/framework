<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('permissions')]
class Permission extends Model
{
    /**
     * Get the roles that have this permission via the role_permissions pivot table.
     *
     * @return array<Role>
     */
    public function roles(): array
    {
        $stmt = DB::raw(
            'SELECT r.* FROM roles r '
            . 'INNER JOIN role_permissions rp ON rp.role_id = r.id '
            . 'WHERE rp.permission_id = :permission_id',
            ['permission_id' => $this->id]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => Role::hydrate($row), $rows);
    }

    /**
     * Find a permission by its name.
     */
    public static function findByName(string $name): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM permissions WHERE name = :name LIMIT 1',
            ['name' => $name]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}