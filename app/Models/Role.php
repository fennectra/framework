<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('roles')]
class Role extends Model
{
    /**
     * Get the permissions assigned to this role via the role_permissions pivot table.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        $stmt = DB::raw(
            'SELECT p.* FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'WHERE rp.role_id = :role_id',
            ['role_id' => $this->id]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => Permission::hydrate($row), $rows);
    }

    /**
     * Get the users assigned to this role via the user_roles pivot table.
     *
     * @return array<User>
     */
    public function users(): array
    {
        $stmt = DB::raw(
            'SELECT u.* FROM users u '
            . 'INNER JOIN user_roles ur ON ur.user_id = u.id '
            . 'WHERE ur.role_id = :role_id AND u.deleted_at IS NULL',
            ['role_id' => $this->id]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => User::hydrate($row), $rows);
    }

    /**
     * Find a role by its name.
     */
    public static function findByName(string $name): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM roles WHERE name = :name LIMIT 1',
            ['name' => $name]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}