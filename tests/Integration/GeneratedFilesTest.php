<?php

namespace Tests\Integration;

class GeneratedFilesTest extends IntegrationTestCase
{
    public function testAuthModuleFilesGenerated(): void
    {
        $files = [
            'app/Models/User.php',
            'app/Models/Role.php',
            'app/Models/Permission.php',
            'app/Models/PersonalAccessToken.php',
            'app/Controllers/Auth/AuthController.php',
            'app/Controllers/Auth/RoleController.php',
            'app/Controllers/Auth/PermissionController.php',
            'app/Middleware/Auth.php',
            'app/Routes/auth.php',
            'database/seeders/AuthSeeder.php',
        ];

        foreach ($files as $file) {
            $this->assertFileExists(
                self::$tempDir . '/' . $file,
                "make:auth should generate {$file}"
            );
        }
    }

    public function testOrganizationModuleFilesGenerated(): void
    {
        $files = [
            'app/Models/Organization.php',
            'app/Models/OrganizationMember.php',
            'app/Models/OrganizationInvitation.php',
            'app/Controllers/OrganizationController.php',
            'app/Routes/organizations.php',
        ];

        foreach ($files as $file) {
            $this->assertFileExists(
                self::$tempDir . '/' . $file,
                "make:organization should generate {$file}"
            );
        }
    }

    public function testEmailModuleFilesGenerated(): void
    {
        $this->assertFileExists(
            self::$tempDir . '/app/Models/EmailTemplate.php',
            'make:email should generate EmailTemplate model'
        );
        $this->assertFileExists(
            self::$tempDir . '/app/Controllers/EmailTemplateController.php',
            'make:email should generate EmailTemplateController'
        );
    }

    public function testUserModelHasRbacMethods(): void
    {
        $content = file_get_contents(self::$tempDir . '/app/Models/User.php');

        $this->assertStringContainsString('function roles()', $content, 'User should have roles() method');
        $this->assertStringContainsString('function hasRole(', $content, 'User should have hasRole() method');
        $this->assertStringContainsString('function hasPermission(', $content, 'User should have hasPermission() method');
        $this->assertStringContainsString('function assignRole(', $content, 'User should have assignRole() method');
        $this->assertStringContainsString('function removeRole(', $content, 'User should have removeRole() method');
    }

    public function testMiddlewareUsesJwtService(): void
    {
        $content = file_get_contents(self::$tempDir . '/app/Middleware/Auth.php');

        $this->assertStringContainsString('JwtService', $content, 'Middleware should use JwtService');
        $this->assertStringNotContainsString('JWT_SECRET', $content, 'Middleware should NOT use JWT_SECRET env var');
        $this->assertStringNotContainsString('base64_decode($payload)', $content, 'Middleware should NOT do manual JWT decoding');
        $this->assertStringContainsString('__auth_user', $content, 'Middleware should store full user object');
    }

    public function testAuthControllerUsesJwtService(): void
    {
        $content = file_get_contents(self::$tempDir . '/app/Controllers/Auth/AuthController.php');

        $this->assertStringContainsString('JwtService', $content, 'AuthController should use JwtService');
        $this->assertStringContainsString('generateAccessToken', $content, 'AuthController should call generateAccessToken');
        $this->assertStringContainsString('generateRefreshToken', $content, 'AuthController should call generateRefreshToken');
    }

    public function testRoutesAreGrouped(): void
    {
        $content = file_get_contents(self::$tempDir . '/app/Routes/auth.php');

        $this->assertStringContainsString("'prefix' => '/auth'", $content, 'Auth routes should be grouped under /auth');
        $this->assertStringContainsString("'prefix' => '/auth/roles'", $content, 'Role routes should be under /auth/roles');
        $this->assertStringContainsString("'prefix' => '/auth/permissions'", $content, 'Permission routes should be under /auth/permissions');
        $this->assertStringContainsString('role:admin', $content, 'Admin routes should require admin role');
    }

    public function testDtoValidationAttributes(): void
    {
        $register = file_get_contents(self::$tempDir . '/app/Dto/Auth/RegisterRequest.php');

        $this->assertStringContainsString('#[Required]', $register, 'RegisterRequest should have Required attribute');
        $this->assertStringContainsString('#[Email]', $register, 'RegisterRequest should have Email attribute');
        $this->assertStringContainsString('#[MinLength', $register, 'RegisterRequest should have MinLength attribute');
    }
}
