<?php

namespace Tests\Integration;

class MigrationTest extends IntegrationTestCase
{
    public function testAuthTablesExist(): void
    {
        $this->assertTrue($this->tableExists('users'), 'Table users should exist');
        $this->assertTrue($this->tableExists('roles'), 'Table roles should exist');
        $this->assertTrue($this->tableExists('permissions'), 'Table permissions should exist');
        $this->assertTrue($this->tableExists('role_permissions'), 'Table role_permissions should exist');
        $this->assertTrue($this->tableExists('user_roles'), 'Table user_roles should exist');
        $this->assertTrue($this->tableExists('personal_access_tokens'), 'Table personal_access_tokens should exist');
    }

    public function testOrganizationTablesExist(): void
    {
        $this->assertTrue($this->tableExists('organizations'), 'Table organizations should exist');
        $this->assertTrue($this->tableExists('organization_members'), 'Table organization_members should exist');
        $this->assertTrue($this->tableExists('organization_invitations'), 'Table organization_invitations should exist');
    }

    public function testEmailTablesExist(): void
    {
        $this->assertTrue($this->tableExists('email_templates'), 'Table email_templates should exist');
    }

    public function testComplianceTablesExist(): void
    {
        $this->assertTrue($this->tableExists('audit_logs') || $this->tableExists('audit_trail'), 'Audit table should exist');
    }

    public function testUsersTableHasRequiredColumns(): void
    {
        $columns = $this->query("PRAGMA table_info(users)");
        $columnNames = array_column($columns, 'name');

        $required = ['id', 'name', 'email', 'password', 'is_active'];
        foreach ($required as $col) {
            $this->assertContains($col, $columnNames, "Column {$col} should exist in users table");
        }
    }

    public function testRolesTableHasRequiredColumns(): void
    {
        $columns = $this->query("PRAGMA table_info(roles)");
        $columnNames = array_column($columns, 'name');

        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
    }

    public function testPermissionsTableHasRequiredColumns(): void
    {
        $columns = $this->query("PRAGMA table_info(permissions)");
        $columnNames = array_column($columns, 'name');

        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
    }
}
