<?php

namespace Tests\Integration;

class AuthRbacTest extends IntegrationTestCase
{
    public function testSeederCreatedRoles(): void
    {
        $roles = $this->query('SELECT name FROM roles ORDER BY name');
        $names = array_column($roles, 'name');

        $this->assertContains('admin', $names);
        $this->assertContains('manager', $names);
        $this->assertContains('user', $names);
    }

    public function testSeederCreatedPermissions(): void
    {
        $permissions = $this->query('SELECT name FROM permissions ORDER BY name');
        $names = array_column($permissions, 'name');

        // Should have CRUD permissions for common resources
        $this->assertContains('users.create', $names);
        $this->assertContains('users.read', $names);
        $this->assertContains('users.update', $names);
        $this->assertContains('users.delete', $names);
        $this->assertContains('roles.create', $names);
        $this->assertContains('organizations.read', $names);
    }

    public function testAdminHasAllPermissions(): void
    {
        $adminRole = $this->queryOne('SELECT id FROM roles WHERE name = :name', ['name' => 'admin']);
        $this->assertNotNull($adminRole);

        $totalPermissions = $this->query('SELECT COUNT(*) as cnt FROM permissions');
        $adminPermissions = $this->query(
            'SELECT COUNT(*) as cnt FROM role_permissions WHERE role_id = :rid',
            ['rid' => $adminRole['id']]
        );

        $this->assertEquals(
            $totalPermissions[0]['cnt'],
            $adminPermissions[0]['cnt'],
            'Admin should have ALL permissions'
        );
    }

    public function testUserHasLimitedPermissions(): void
    {
        $userRole = $this->queryOne('SELECT id FROM roles WHERE name = :name', ['name' => 'user']);
        $this->assertNotNull($userRole);

        $userPermissions = $this->query(
            'SELECT p.name FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'WHERE rp.role_id = :rid',
            ['rid' => $userRole['id']]
        );
        $names = array_column($userPermissions, 'name');

        // User should have read-only
        $this->assertContains('users.read', $names);
        $this->assertContains('organizations.read', $names);
        // User should NOT have write access
        $this->assertNotContains('users.create', $names);
        $this->assertNotContains('users.delete', $names);
        $this->assertNotContains('roles.create', $names);
    }

    public function testManagerHasIntermediatePermissions(): void
    {
        $managerRole = $this->queryOne('SELECT id FROM roles WHERE name = :name', ['name' => 'manager']);
        $this->assertNotNull($managerRole);

        $managerPermissions = $this->query(
            'SELECT p.name FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'WHERE rp.role_id = :rid',
            ['rid' => $managerRole['id']]
        );
        $names = array_column($managerPermissions, 'name');

        // Manager should have some write access
        $this->assertContains('users.read', $names);
        $this->assertContains('users.update', $names);
        $this->assertContains('organizations.create', $names);
        // But not role/permission management
        $this->assertNotContains('roles.create', $names);
        $this->assertNotContains('permissions.create', $names);
    }

    public function testDefaultAdminUserExists(): void
    {
        $admin = $this->queryOne('SELECT * FROM users WHERE email = :email', ['email' => 'admin@fennectra.dev']);

        $this->assertNotNull($admin, 'Default admin user should exist');
        $this->assertEquals('Admin', $admin['name']);
        $this->assertEquals(1, (int) $admin['is_active']);
        $this->assertTrue(password_verify('password123', $admin['password']));
    }

    public function testAdminUserHasAdminRole(): void
    {
        $admin = $this->queryOne('SELECT id FROM users WHERE email = :email', ['email' => 'admin@fennectra.dev']);
        $adminRole = $this->queryOne('SELECT id FROM roles WHERE name = :name', ['name' => 'admin']);

        $this->assertNotNull($admin);
        $this->assertNotNull($adminRole);

        $pivot = $this->queryOne(
            'SELECT * FROM user_roles WHERE user_id = :uid AND role_id = :rid',
            ['uid' => $admin['id'], 'rid' => $adminRole['id']]
        );

        $this->assertNotNull($pivot, 'Admin user should have admin role via user_roles pivot');
    }

    public function testUserRegistration(): void
    {
        $now = date('Y-m-d H:i:s');
        $password = password_hash('testpass123', PASSWORD_BCRYPT);

        $this->query(
            'INSERT INTO users (name, email, password, is_active, created_at, updated_at) VALUES (:name, :email, :password, 0, :now, :now)',
            ['name' => 'Test User', 'email' => 'test@example.com', 'password' => $password, 'now' => $now]
        );

        $user = $this->queryOne('SELECT * FROM users WHERE email = :email', ['email' => 'test@example.com']);

        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user['name']);
        $this->assertEquals(0, (int) $user['is_active']);
        $this->assertTrue(password_verify('testpass123', $user['password']));
    }

    public function testAssignRoleToUser(): void
    {
        // Get test user and user role
        $user = $this->queryOne('SELECT id FROM users WHERE email = :email', ['email' => 'test@example.com']);
        $userRole = $this->queryOne('SELECT id FROM roles WHERE name = :name', ['name' => 'user']);

        if (!$user || !$userRole) {
            $this->markTestSkipped('Requires testUserRegistration to run first');
        }

        // Assign role
        $this->query(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $user['id'], 'rid' => $userRole['id']]
        );

        // Verify
        $roles = $this->query(
            'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid',
            ['uid' => $user['id']]
        );

        $this->assertCount(1, $roles);
        $this->assertEquals('user', $roles[0]['name']);
    }

    public function testPermissionChainWorks(): void
    {
        // Get admin user
        $admin = $this->queryOne('SELECT id FROM users WHERE email = :email', ['email' => 'admin@fennectra.dev']);

        // Check permission via the full chain: user -> user_roles -> roles -> role_permissions -> permissions
        $permissions = $this->query(
            'SELECT DISTINCT p.name FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'INNER JOIN user_roles ur ON ur.role_id = rp.role_id '
            . 'WHERE ur.user_id = :uid',
            ['uid' => $admin['id']]
        );

        $names = array_column($permissions, 'name');

        $this->assertNotEmpty($names, 'Admin should have permissions via chain');
        $this->assertContains('users.create', $names);
        $this->assertContains('users.delete', $names);
        $this->assertContains('organizations.read', $names);
    }
}
