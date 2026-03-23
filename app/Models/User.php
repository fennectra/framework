<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('users')]
class User extends Model
{
    /** Enable soft deletes */
    protected static bool $softDeletes = true;

    /** @var array<string, string> */
    protected static array $casts = [
        'id' => 'int',
        'is_active' => 'bool',
    ];

    /**
     * Get the primary role (belongsTo relationship via role_id column).
     * Kept for backward compatibility.
     */
    public function role(): ?Role
    {
        if (empty($this->role_id)) {
            return null;
        }

        return Role::find($this->role_id);
    }

    /**
     * Get all roles assigned to this user via the user_roles pivot table.
     *
     * @return array<Role>
     */
    public function roles(): array
    {
        $stmt = DB::raw(
            'SELECT r.* FROM roles r '
            . 'INNER JOIN user_roles ur ON ur.role_id = r.id '
            . 'WHERE ur.user_id = :user_id',
            ['user_id' => $this->id]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => Role::hydrate($row), $rows);
    }

    /**
     * Get all permissions for this user via user_roles -> roles -> role_permissions -> permissions.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        $stmt = DB::raw(
            'SELECT DISTINCT p.* FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'INNER JOIN user_roles ur ON ur.role_id = rp.role_id '
            . 'WHERE ur.user_id = :user_id',
            ['user_id' => $this->id]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => Permission::hydrate($row), $rows);
    }

    /**
     * Check if the user has a specific role by name.
     */
    public function hasRole(string $name): bool
    {
        $stmt = DB::raw(
            'SELECT COUNT(*) as cnt FROM user_roles ur '
            . 'INNER JOIN roles r ON r.id = ur.role_id '
            . 'WHERE ur.user_id = :user_id AND r.name = :role_name',
            ['user_id' => $this->id, 'role_name' => $name]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Check if the user has a specific permission (via any of their roles).
     */
    public function hasPermission(string $name): bool
    {
        $stmt = DB::raw(
            'SELECT COUNT(*) as cnt FROM user_roles ur '
            . 'INNER JOIN role_permissions rp ON rp.role_id = ur.role_id '
            . 'INNER JOIN permissions p ON p.id = rp.permission_id '
            . 'WHERE ur.user_id = :user_id AND p.name = :permission_name',
            ['user_id' => $this->id, 'permission_name' => $name]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Assign a role to this user via the user_roles pivot table.
     * Accepts a role name (string) or role ID (int).
     */
    public function assignRole(string|int $roleNameOrId): void
    {
        if (is_string($roleNameOrId)) {
            $role = Role::findByName($roleNameOrId);
            if (!$role) {
                throw new \InvalidArgumentException("Role '{$roleNameOrId}' not found.");
            }
            $roleId = $role->id;
        } else {
            $roleId = $roleNameOrId;
        }

        // Avoid duplicate entries
        $stmt = DB::raw(
            'SELECT COUNT(*) as cnt FROM user_roles WHERE user_id = :user_id AND role_id = :role_id',
            ['user_id' => $this->id, 'role_id' => $roleId]
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (($row['cnt'] ?? 0) === 0) {
            DB::raw(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                ['user_id' => $this->id, 'role_id' => $roleId]
            );
        }
    }

    /**
     * Remove a role from this user via the user_roles pivot table.
     * Accepts a role name (string) or role ID (int).
     */
    public function removeRole(string|int $roleNameOrId): void
    {
        if (is_string($roleNameOrId)) {
            $role = Role::findByName($roleNameOrId);
            if (!$role) {
                throw new \InvalidArgumentException("Role '{$roleNameOrId}' not found.");
            }
            $roleId = $role->id;
        } else {
            $roleId = $roleNameOrId;
        }

        DB::raw(
            'DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id',
            ['user_id' => $this->id, 'role_id' => $roleId]
        );
    }

    /**
     * Get the personal access tokens for this user.
     *
     * @return array<PersonalAccessToken>
     */
    public function tokens(): array
    {
        return PersonalAccessToken::where('user_id', '=', $this->id)->get();
    }

    /**
     * Find a user by email address.
     */
    public static function findByEmail(string $email): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Find a user by email and API token.
     */
    public static function findByEmailAndToken(string $email, string $token): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE email = :email AND api_token = :token AND deleted_at IS NULL LIMIT 1',
            ['email' => $email, 'token' => $token]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Find a user by activation token.
     */
    public static function findByActivationToken(string $token): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE activation_token = :token AND deleted_at IS NULL LIMIT 1',
            ['token' => $token]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Find a user by password reset token.
     */
    public static function findByResetToken(string $token): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE reset_token = :token AND deleted_at IS NULL LIMIT 1',
            ['token' => $token]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}