<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiUsersController
{
    use UiHelper;

    public function list(): void
    {
        $search = $this->queryString('search');
        $role = $this->queryString('role');
        $status = $this->queryString('status');

        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = '(email LIKE ? OR name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($role) {
            $conditions[] = 'id IN (SELECT user_id FROM user_roles WHERE role_id = (SELECT id FROM roles WHERE name = ?))';
            $params[] = $role;
        }
        if ($status === 'active') {
            $conditions[] = 'is_active = true';
        } elseif ($status === 'inactive') {
            $conditions[] = 'is_active = false';
        }

        $where = $conditions ? implode(' AND ', $conditions) : '';

        try {
            $result = $this->paginate('users', $where, $params);

            foreach ($result['data'] as &$row) {
                unset($row['password']);
                $roles = DB::raw(
                    'SELECT r.id, r.name FROM roles r JOIN user_roles ru ON r.id = ru.role_id WHERE ru.user_id = ?',
                    [$row['id']]
                )->fetchAll();
                $row['roles'] = $roles;
            }

            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function show(int $id): void
    {
        try {
            $user = DB::raw('SELECT * FROM users WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$user) {
                Response::json(['error' => 'User not found'], 404);

                return;
            }

            unset($user['password']);

            $user['roles'] = DB::raw(
                'SELECT r.id, r.name FROM roles r JOIN user_roles ru ON r.id = ru.role_id WHERE ru.user_id = ?',
                [$id]
            )->fetchAll();

            $user['permissions'] = DB::raw(
                'SELECT DISTINCT p.id, p.name FROM permissions p
                 JOIN role_permissions pr ON p.id = pr.permission_id
                 JOIN user_roles ru ON pr.role_id = ru.role_id
                 WHERE ru.user_id = ?',
                [$id]
            )->fetchAll();

            Response::json($user);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(int $id): void
    {
        $body = $this->body();
        $fields = [];
        $params = [];

        foreach (['name', 'email'] as $field) {
            if (isset($body[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $body[$field];
            }
        }

        if (isset($body['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $body['is_active'] ? 'true' : 'false';
        }

        if (!$fields) {
            Response::json(['error' => 'No fields to update'], 422);

            return;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        try {
            DB::raw('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

            if (isset($body['roles']) && is_array($body['roles'])) {
                DB::raw('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                foreach ($body['roles'] as $roleId) {
                    DB::raw('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, (int) $roleId]);
                }
            }

            SecurityLogger::track('user.updated', ['id' => $id, 'by' => 'admin_ui']);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggle(int $id): void
    {
        try {
            DB::raw('UPDATE users SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
            SecurityLogger::track('user.toggled', ['id' => $id, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function resetPassword(int $id): void
    {
        $body = $this->body();
        $password = $body['password'] ?? '';

        if (strlen($password) < 12) {
            Response::json(['error' => 'Password must be at least 12 characters'], 422);

            return;
        }

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            DB::raw('UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$hash, $id]);
            SecurityLogger::track('user.password_reset', ['id' => $id, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            DB::raw('UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
            SecurityLogger::track('user.deleted', ['id' => $id, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Roles ───

    public function roles(): void
    {
        try {
            $roles = DB::raw('SELECT * FROM roles ORDER BY id ASC')->fetchAll();

            foreach ($roles as &$role) {
                $role['permissions'] = DB::raw(
                    'SELECT p.id, p.name FROM permissions p JOIN role_permissions pr ON p.id = pr.permission_id WHERE pr.role_id = ?',
                    [$role['id']]
                )->fetchAll();
                $role['users_count'] = (int) (DB::raw(
                    'SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = ?',
                    [$role['id']]
                )->fetchAll()[0]['cnt'] ?? 0);
            }

            Response::json($roles);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function createRole(): void
    {
        $body = $this->body();
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';

        if (!$name) {
            Response::json(['error' => 'Name is required'], 422);

            return;
        }

        try {
            DB::raw(
                'INSERT INTO roles (name, description, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $description]
            );
            SecurityLogger::track('role.created', ['name' => $name, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateRole(int $id): void
    {
        $body = $this->body();
        $fields = [];
        $params = [];

        if (isset($body['name'])) {
            $fields[] = 'name = ?';
            $params[] = $body['name'];
        }
        if (isset($body['description'])) {
            $fields[] = 'description = ?';
            $params[] = $body['description'];
        }

        if ($fields) {
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $params[] = $id;
            DB::raw('UPDATE roles SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        }

        if (isset($body['permissions']) && is_array($body['permissions'])) {
            DB::raw('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            foreach ($body['permissions'] as $permId) {
                DB::raw('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$id, (int) $permId]);
            }
        }

        SecurityLogger::track('role.updated', ['id' => $id, 'by' => 'admin_ui']);
        Response::json(['success' => true]);
    }

    public function deleteRole(int $id): void
    {
        try {
            DB::raw('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            DB::raw('DELETE FROM user_roles WHERE role_id = ?', [$id]);
            DB::raw('DELETE FROM roles WHERE id = ?', [$id]);
            SecurityLogger::track('role.deleted', ['id' => $id, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Permissions ───

    public function permissions(): void
    {
        try {
            $permissions = DB::raw('SELECT * FROM permissions ORDER BY name ASC')->fetchAll();
            Response::json($permissions);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function createPermission(): void
    {
        $body = $this->body();
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';

        if (!$name) {
            Response::json(['error' => 'Name is required'], 422);

            return;
        }

        try {
            DB::raw(
                'INSERT INTO permissions (name, description, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $description]
            );

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletePermission(int $id): void
    {
        try {
            DB::raw('DELETE FROM role_permissions WHERE permission_id = ?', [$id]);
            DB::raw('DELETE FROM permissions WHERE id = ?', [$id]);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
