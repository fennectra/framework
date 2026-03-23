<?php

use Fennec\Core\DB;
use Fennec\Core\Migration\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // Default roles
        $roles = [
            ['name' => 'admin', 'description' => 'Full access to all resources'],
            ['name' => 'manager', 'description' => 'Manage users and content'],
            ['name' => 'user', 'description' => 'Standard user access'],
        ];

        foreach ($roles as $role) {
            DB::raw(
                'INSERT INTO roles (name, description, created_at, updated_at) VALUES (:name, :description, :now, :now)',
                ['name' => $role['name'], 'description' => $role['description'], 'now' => $now]
            );
        }

        // Default permissions (CRUD for common resources)
        $resources = ['users', 'roles', 'permissions', 'organizations'];
        $actions = ['create', 'read', 'update', 'delete'];
        $permissionIds = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $name = "{$resource}.{$action}";
                DB::raw(
                    'INSERT INTO permissions (name, description, created_at, updated_at) VALUES (:name, :description, :now, :now)',
                    ['name' => $name, 'description' => ucfirst($action) . ' ' . $resource, 'now' => $now]
                );
                $stmt = DB::raw('SELECT id FROM permissions WHERE name = :name', ['name' => $name]);
                $permissionIds[$name] = $stmt->fetchColumn();
            }
        }

        // Get role IDs
        $adminId = DB::raw('SELECT id FROM roles WHERE name = :name', ['name' => 'admin'])->fetchColumn();
        $managerId = DB::raw('SELECT id FROM roles WHERE name = :name', ['name' => 'manager'])->fetchColumn();
        $userId = DB::raw('SELECT id FROM roles WHERE name = :name', ['name' => 'user'])->fetchColumn();

        // Admin gets ALL permissions
        foreach ($permissionIds as $permId) {
            DB::raw(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                ['rid' => $adminId, 'pid' => $permId]
            );
        }

        // Manager gets read + update on users and organizations
        $managerPerms = ['users.read', 'users.update', 'organizations.create', 'organizations.read', 'organizations.update', 'organizations.delete'];
        foreach ($managerPerms as $perm) {
            if (isset($permissionIds[$perm])) {
                DB::raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                    ['rid' => $managerId, 'pid' => $permissionIds[$perm]]
                );
            }
        }

        // User gets read-only on users and organizations
        $userPerms = ['users.read', 'organizations.read'];
        foreach ($userPerms as $perm) {
            if (isset($permissionIds[$perm])) {
                DB::raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                    ['rid' => $userId, 'pid' => $permissionIds[$perm]]
                );
            }
        }

        // Create default admin user
        DB::raw(
            'INSERT INTO users (name, email, password, is_active, activated_at, created_at, updated_at) VALUES (:name, :email, :password, 1, :now, :now, :now)',
            [
                'name' => 'Admin',
                'email' => 'admin@fennectra.dev',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'now' => $now,
            ]
        );

        // Assign admin role to admin user via user_roles pivot
        $adminUserId = DB::raw('SELECT id FROM users WHERE email = :email', ['email' => 'admin@fennectra.dev'])->fetchColumn();
        DB::raw(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $adminUserId, 'rid' => $adminId]
        );

        echo "  AuthSeeder: 3 roles, " . count($permissionIds) . " permissions, 1 admin user created.\n";
    }
}