<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:auth', 'Generate authentication module: users, roles, permissions, tokens')]
class MakeAuthCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Authentication — Module Generation          ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migrations
        $this->createMigrations();

        // 2. Models
        $this->createModel('User', $this->modelUser());
        $this->createModel('Role', $this->modelRole());
        $this->createModel('Permission', $this->modelPermission());
        $this->createModel('PersonalAccessToken', $this->modelPersonalAccessToken());

        // 3. DTOs
        $this->createDto('RegisterRequest', $this->dtoRegisterRequest());
        $this->createDto('LoginRequest', $this->dtoLoginRequest());
        $this->createDto('ForgotPasswordRequest', $this->dtoForgotPasswordRequest());
        $this->createDto('ResetPasswordRequest', $this->dtoResetPasswordRequest());
        $this->createDto('AuthResponse', $this->dtoAuthResponse());
        $this->createDto('UserResponse', $this->dtoUserResponse());
        $this->createDto('RoleRequest', $this->dtoRoleRequest());
        $this->createDto('RoleResponse', $this->dtoRoleResponse());
        $this->createDto('PermissionRequest', $this->dtoPermissionRequest());
        $this->createDto('PermissionResponse', $this->dtoPermissionResponse());

        // 4. Controllers
        $this->createController('AuthController', $this->controllerAuth());
        $this->createController('RoleController', $this->controllerRole());
        $this->createController('PermissionController', $this->controllerPermission());

        // 5. Middleware
        $this->createMiddleware();

        // 6. Mail templates
        $this->createMailTemplate('AccountActivation', $this->mailAccountActivation());
        $this->createMailTemplate('PasswordReset', $this->mailPasswordReset());
        $this->createMailTemplate('Welcome', $this->mailWelcome());

        // 7. Routes
        $this->createRoutes();

        // 8. Seeder
        $this->createSeeder();

        // Summary
        echo "\n\033[1;32m  ✓ Authentication module generated successfully\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mAPI Routes (public):\033[0m\n";
        echo "    POST   /auth/register                Register a new user\n";
        echo "    POST   /auth/login                   Authenticate user\n";
        echo "    GET    /auth/activate/{token}         Activate account\n";
        echo "    POST   /auth/forgot-password          Request password reset\n";
        echo "    POST   /auth/reset-password           Reset password with token\n";

        echo "\n  \033[33mAPI Routes (authenticated):\033[0m\n";
        echo "    POST   /auth/logout                  Logout current user\n";
        echo "    GET    /auth/me                      Get current user profile\n";

        echo "\n  \033[33mAPI Routes (admin only):\033[0m\n";
        echo "    GET    /auth/roles                   List roles\n";
        echo "    POST   /auth/roles                   Create role\n";
        echo "    GET    /auth/roles/{id}              Show role\n";
        echo "    PUT    /auth/roles/{id}              Update role\n";
        echo "    DELETE /auth/roles/{id}              Delete role\n";
        echo "    POST   /auth/roles/{id}/permissions  Assign permissions to role\n";
        echo "    GET    /auth/permissions              List permissions\n";
        echo "    POST   /auth/permissions              Create permission\n";
        echo "    GET    /auth/permissions/{id}         Show permission\n";
        echo "    PUT    /auth/permissions/{id}         Update permission\n";
        echo "    DELETE /auth/permissions/{id}         Delete permission\n";

        echo "\n\033[36m  Run: ./forge migrate\033[0m then \033[36m./forge db:seed\033[0m\n\n";

        return 0;
    }

    // ─── Migrations ───────────────────────────────────────────────

    private function createMigrations(): void
    {
        $tables = [
            'create_roles' => 'roles',
            'create_permissions' => 'permissions',
            'create_role_permissions' => 'role_permissions',
            'create_user_roles' => 'user_roles',
            'create_users' => 'users',
            'create_personal_access_tokens' => 'personal_access_tokens',
        ];

        $dir = FENNEC_BASE_PATH . '/database/migrations';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $driver = Env::get('DB_DRIVER', 'pgsql');
        $timestamp = date('Y_m_d_His');
        $i = 0;

        foreach ($tables as $migrationName => $tableName) {
            // Check if migration already exists
            $exists = false;

            foreach (glob($dir . '/*.php') as $file) {
                if (str_contains($file, $migrationName)) {
                    $exists = true;

                    break;
                }
            }

            if ($exists) {
                echo "  \033[33m⚠ Migration {$migrationName} already exists, skipped\033[0m\n";

                continue;
            }

            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $filename = "{$timestamp}_{$suffix}_{$migrationName}";

            $upMethod = "migration_{$tableName}_up";
            $upMethod = str_replace('.', '_', $upMethod);
            $up = $this->$upMethod($driver);
            $down = "DROP TABLE IF EXISTS {$tableName}";

            $lines = [
                '<?php',
                '',
                'return [',
                '    \'up\' => \'' . str_replace("'", "\\'", $up) . '\',',
                '    \'down\' => \'' . $down . '\',',
                '];',
                '',
            ];

            file_put_contents("{$dir}/{$filename}.php", implode("\n", $lines));
            $this->created[] = "database/migrations/{$filename}.php";
            $i++;
        }
    }

    // ─── Migration SQL: roles ─────────────────────────────────────

    private function migration_roles_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS roles ('
                . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' name VARCHAR(100) NOT NULL UNIQUE,'
                . ' guard_name VARCHAR(50) NOT NULL DEFAULT \'web\','
                . ' description VARCHAR(255) DEFAULT NULL,'
                . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS roles ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' name TEXT NOT NULL UNIQUE,'
                . ' guard_name TEXT NOT NULL DEFAULT \'web\','
                . ' description TEXT DEFAULT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS roles ('
                . ' id SERIAL PRIMARY KEY,'
                . ' name VARCHAR(100) NOT NULL UNIQUE,'
                . ' guard_name VARCHAR(50) NOT NULL DEFAULT \'web\','
                . ' description VARCHAR(255) DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW(),'
                . ' updated_at TIMESTAMP DEFAULT NOW()'
                . ')',
        };
    }

    // ─── Migration SQL: permissions ───────────────────────────────

    private function migration_permissions_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS permissions ('
                . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' name VARCHAR(100) NOT NULL UNIQUE,'
                . ' guard_name VARCHAR(50) NOT NULL DEFAULT \'web\','
                . ' description VARCHAR(255) DEFAULT NULL,'
                . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS permissions ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' name TEXT NOT NULL UNIQUE,'
                . ' guard_name TEXT NOT NULL DEFAULT \'web\','
                . ' description TEXT DEFAULT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS permissions ('
                . ' id SERIAL PRIMARY KEY,'
                . ' name VARCHAR(100) NOT NULL UNIQUE,'
                . ' guard_name VARCHAR(50) NOT NULL DEFAULT \'web\','
                . ' description VARCHAR(255) DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW(),'
                . ' updated_at TIMESTAMP DEFAULT NOW()'
                . ')',
        };
    }

    // ─── Migration SQL: role_permissions ──────────────────────────

    private function migration_role_permissions_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS role_permissions ('
                . ' role_id INT UNSIGNED NOT NULL,'
                . ' permission_id INT UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (role_id, permission_id),'
                . ' CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,'
                . ' CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS role_permissions ('
                . ' role_id INTEGER NOT NULL,'
                . ' permission_id INTEGER NOT NULL,'
                . ' PRIMARY KEY (role_id, permission_id),'
                . ' FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,'
                . ' FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS role_permissions ('
                . ' role_id INTEGER NOT NULL,'
                . ' permission_id INTEGER NOT NULL,'
                . ' PRIMARY KEY (role_id, permission_id),'
                . ' CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,'
                . ' CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE'
                . ')',
        };
    }

    // ─── Migration SQL: user_roles ────────────────────────────────

    private function migration_user_roles_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS user_roles ('
                . ' user_id INT UNSIGNED NOT NULL,'
                . ' role_id INT UNSIGNED NOT NULL,'
                . ' PRIMARY KEY (user_id, role_id),'
                . ' CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,'
                . ' CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS user_roles ('
                . ' user_id INTEGER NOT NULL,'
                . ' role_id INTEGER NOT NULL,'
                . ' PRIMARY KEY (user_id, role_id),'
                . ' FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,'
                . ' FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS user_roles ('
                . ' user_id INTEGER NOT NULL,'
                . ' role_id INTEGER NOT NULL,'
                . ' PRIMARY KEY (user_id, role_id),'
                . ' CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,'
                . ' CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE'
                . ')',
        };
    }

    // ─── Migration SQL: users ─────────────────────────────────────

    private function migration_users_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS users ('
                . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' email VARCHAR(255) NOT NULL UNIQUE,'
                . ' password VARCHAR(255) NOT NULL,'
                . ' is_active TINYINT(1) NOT NULL DEFAULT 1,'
                . ' activation_token VARCHAR(64) DEFAULT NULL,'
                . ' activated_at DATETIME DEFAULT NULL,'
                . ' reset_token VARCHAR(64) DEFAULT NULL,'
                . ' reset_token_expires_at DATETIME DEFAULT NULL,'
                . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' deleted_at DATETIME DEFAULT NULL'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS users ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' name TEXT NOT NULL,'
                . ' email TEXT NOT NULL UNIQUE,'
                . ' password TEXT NOT NULL,'
                . ' is_active INTEGER NOT NULL DEFAULT 1,'
                . ' activation_token TEXT DEFAULT NULL,'
                . ' activated_at TEXT DEFAULT NULL,'
                . ' reset_token TEXT DEFAULT NULL,'
                . ' reset_token_expires_at TEXT DEFAULT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' deleted_at TEXT DEFAULT NULL'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS users ('
                . ' id SERIAL PRIMARY KEY,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' email VARCHAR(255) NOT NULL UNIQUE,'
                . ' password VARCHAR(255) NOT NULL,'
                . ' is_active SMALLINT NOT NULL DEFAULT 1,'
                . ' activation_token VARCHAR(64) DEFAULT NULL,'
                . ' activated_at TIMESTAMP DEFAULT NULL,'
                . ' reset_token VARCHAR(64) DEFAULT NULL,'
                . ' reset_token_expires_at TIMESTAMP DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW(),'
                . ' updated_at TIMESTAMP DEFAULT NOW(),'
                . ' deleted_at TIMESTAMP DEFAULT NULL'
                . ')',
        };
    }

    // ─── Migration SQL: personal_access_tokens ────────────────────

    private function migration_personal_access_tokens_up(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS personal_access_tokens ('
                . ' id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' user_id INT UNSIGNED NOT NULL,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' token VARCHAR(64) NOT NULL UNIQUE,'
                . ' abilities TEXT DEFAULT NULL,'
                . ' last_used_at DATETIME DEFAULT NULL,'
                . ' expires_at DATETIME DEFAULT NULL,'
                . ' created_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,'
                . ' CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS personal_access_tokens ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' user_id INTEGER NOT NULL,'
                . ' name TEXT NOT NULL,'
                . ' token TEXT NOT NULL UNIQUE,'
                . ' abilities TEXT DEFAULT NULL,'
                . ' last_used_at TEXT DEFAULT NULL,'
                . ' expires_at TEXT DEFAULT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
                . ')',
            default => 'CREATE TABLE IF NOT EXISTS personal_access_tokens ('
                . ' id SERIAL PRIMARY KEY,'
                . ' user_id INTEGER NOT NULL,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' token VARCHAR(64) NOT NULL UNIQUE,'
                . ' abilities TEXT DEFAULT NULL,'
                . ' last_used_at TIMESTAMP DEFAULT NULL,'
                . ' expires_at TIMESTAMP DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW(),'
                . ' updated_at TIMESTAMP DEFAULT NOW(),'
                . ' CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
                . ')',
        };
    }

    // ─── Models ───────────────────────────────────────────────────

    private function createModel(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Models/{$name}.php";
    }

    private function modelUser(): string
    {
        return <<<'PHP'
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
PHP;
    }

    private function modelRole(): string
    {
        return <<<'PHP'
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
PHP;
    }

    private function modelPermission(): string
    {
        return <<<'PHP'
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
PHP;
    }

    private function modelPersonalAccessToken(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('personal_access_tokens')]
class PersonalAccessToken extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
    ];

    /**
     * Get the user who owns this token.
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Find a token by its plain-text value.
     */
    public static function findByToken(string $token): ?static
    {
        $hashed = hash('sha256', $token);

        $stmt = DB::raw(
            'SELECT * FROM personal_access_tokens WHERE token = :token LIMIT 1',
            ['token' => $hashed]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}
PHP;
    }

    // ─── DTOs ─────────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Auth";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Auth/{$name}.php";
    }

    private function dtoRegisterRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\MinLength;
use Fennec\Attributes\Required;

readonly class RegisterRequest
{
    public function __construct(
        #[Required]
        #[Description('Full name of the user')]
        public string $name,
        #[Required]
        #[Email]
        #[Description('Email address')]
        public string $email,
        #[Required]
        #[MinLength(8)]
        #[Description('Password (min 8 characters)')]
        public string $password,
    ) {
    }
}
PHP;
    }

    private function dtoLoginRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class LoginRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address')]
        public string $email,
        #[Required]
        #[Description('Password')]
        public string $password,
    ) {
    }
}
PHP;
    }

    private function dtoForgotPasswordRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class ForgotPasswordRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address of the account')]
        public string $email,
    ) {
    }
}
PHP;
    }

    private function dtoResetPasswordRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\MinLength;
use Fennec\Attributes\Required;

readonly class ResetPasswordRequest
{
    public function __construct(
        #[Required]
        #[Description('Password reset token')]
        public string $token,
        #[Required]
        #[MinLength(8)]
        #[Description('New password (min 8 characters)')]
        public string $password,
    ) {
    }
}
PHP;
    }

    private function dtoAuthResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class AuthResponse
{
    public function __construct(
        #[Description('JWT access token')]
        public string $access_token,
        #[Description('Refresh token')]
        public ?string $refresh_token = null,
        #[Description('Token expiration time in seconds')]
        public ?int $expires_in = null,
        #[Description('Authenticated user')]
        public ?UserResponse $user = null,
    ) {
    }
}
PHP;
    }

    private function dtoUserResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class UserResponse
{
    public function __construct(
        #[Description('User ID')]
        public int $id,
        #[Description('Full name')]
        public string $name,
        #[Description('Email address')]
        public string $email,
        #[Description('Assigned roles')]
        public array $roles = [],
        #[Description('Whether the account is active')]
        public bool $is_active = true,
        #[Description('Account creation date')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoRoleRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class RoleRequest
{
    public function __construct(
        #[Required]
        #[Description('Role name')]
        public string $name,
        #[Description('Role description')]
        public ?string $description = null,
    ) {
    }
}
PHP;
    }

    private function dtoRoleResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class RoleResponse
{
    public function __construct(
        #[Description('Role ID')]
        public int $id,
        #[Description('Role name')]
        public string $name,
        #[Description('Role description')]
        public ?string $description = null,
        #[Description('Assigned permissions')]
        public array $permissions = [],
    ) {
    }
}
PHP;
    }

    private function dtoPermissionRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class PermissionRequest
{
    public function __construct(
        #[Required]
        #[Description('Permission name')]
        public string $name,
        #[Description('Permission description')]
        public ?string $description = null,
    ) {
    }
}
PHP;
    }

    private function dtoPermissionResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Auth;

use Fennec\Attributes\Description;

readonly class PermissionResponse
{
    public function __construct(
        #[Description('Permission ID')]
        public int $id,
        #[Description('Permission name')]
        public string $name,
        #[Description('Permission description')]
        public ?string $description = null,
    ) {
    }
}
PHP;
    }

    // ─── Controllers ──────────────────────────────────────────────

    private function createController(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Controllers/Auth";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Controllers/Auth/{$name}.php";
    }

    private function controllerAuth(): string
    {
        return <<<'PHP'
<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\AuthResponse;
use App\Dto\Auth\ForgotPasswordRequest;
use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Auth\ResetPasswordRequest;
use App\Dto\Auth\UserResponse;
use App\Models\User;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\Env;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;
use Fennec\Core\Validator;

class AuthController
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {
    }

    #[ApiDescription('Register a new user', 'Creates an inactive account and sends an activation email.')]
    #[ApiStatus(201, 'User registered successfully')]
    #[ApiStatus(422, 'Validation error')]
    #[ApiStatus(409, 'Email already in use')]
    public function register(RegisterRequest $request): array
    {
        // DTO validated automatically by Router injection

        $existing = User::findByEmail($request->email);
        if ($existing) {
            throw new HttpException(409, 'Email already in use.');
        }

        $activationToken = bin2hex(random_bytes(32));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => password_hash($request->password, PASSWORD_BCRYPT),
            'is_active' => 0,
            'activation_token' => $activationToken,
            'role_id' => 3,
        ]);

        // Send activation email (if mailer is configured)
        $appUrl = Env::get('APP_URL', 'http://localhost');
        $activationUrl = "{$appUrl}/auth/activate/{$activationToken}";

        try {
            \Fennec\Core\Mail\Mailer::sendTemplate($request->email, 'account_activation', ['name' => $request->name, 'activation_url' => $activationUrl]);
        } catch (\Throwable $e) {
            // Silently fail — user is created, activation link is in DB
        }

        return [
            'status' => 'ok',
            'message' => 'Registration successful. Please check your email to activate your account.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    #[ApiDescription('Authenticate user', 'Verifies credentials and returns a JWT token.')]
    #[ApiStatus(200, 'Login successful')]
    #[ApiStatus(401, 'Invalid credentials')]
    #[ApiStatus(403, 'Account not activated')]
    public function login(LoginRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByEmail($request->email);

        if (!$user || !password_verify($request->password, $user->password)) {
            throw new HttpException(401, 'Invalid credentials.');
        }

        if (!$user->is_active) {
            throw new HttpException(403, 'Account not activated. Please check your email.');
        }

        $accessToken = $this->jwtService->generateAccessToken($user->email);
        $refreshToken = $this->jwtService->generateRefreshToken($user->email, $accessToken['rand']);

        $role = $user->role;

        return [
            'status' => 'ok',
            'data' => [
                'access_token' => $accessToken['token'],
                'refresh_token' => $refreshToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role ? $role->name : null,
                    'is_active' => (bool) $user->is_active,
                    'created_at' => $user->created_at,
                ],
            ],
        ];
    }

    #[ApiDescription('Activate user account', 'Activates the account using the activation token.')]
    #[ApiStatus(200, 'Account activated')]
    #[ApiStatus(404, 'Invalid activation token')]
    public function activate(string $token): array
    {
        $user = User::findByActivationToken($token);

        if (!$user) {
            throw new HttpException(404, 'Invalid or expired activation token.');
        }

        $user->update([
            'is_active' => 1,
            'activation_token' => null,
            'activated_at' => date('Y-m-d H:i:s'),
        ]);

        // Send welcome email
        try {
            $serviceName = Env::get('APP_NAME', 'Fennectra');
            \Fennec\Core\Mail\Mailer::sendTemplate($user->email, 'welcome', ['name' => $user->name, 'service' => $serviceName]);
        } catch (\Throwable $e) {
            // Silently fail
        }

        return [
            'status' => 'ok',
            'message' => 'Account activated successfully. You can now log in.',
        ];
    }

    #[ApiDescription('Request password reset', 'Sends a password reset email with a token.')]
    #[ApiStatus(200, 'Reset email sent')]
    #[ApiStatus(422, 'Validation error')]
    public function forgotPassword(ForgotPasswordRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByEmail($request->email);

        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $user->update([
                'reset_token' => $resetToken,
                'reset_token_expires_at' => $expiresAt,
            ]);

            $appUrl = Env::get('APP_URL', 'http://localhost');
            $resetUrl = "{$appUrl}/auth/reset-password?token={$resetToken}";

            try {
                \Fennec\Core\Mail\Mailer::sendTemplate($user->email, 'password_reset', ['name' => $user->name, 'reset_url' => $resetUrl]);
            } catch (\Throwable $e) {
                // Silently fail
            }
        }

        // Always return success to prevent email enumeration
        return [
            'status' => 'ok',
            'message' => 'If the email exists, a password reset link has been sent.',
        ];
    }

    #[ApiDescription('Reset password', 'Sets a new password using the reset token.')]
    #[ApiStatus(200, 'Password reset successful')]
    #[ApiStatus(400, 'Invalid or expired token')]
    #[ApiStatus(422, 'Validation error')]
    public function resetPassword(ResetPasswordRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByResetToken($request->token);

        if (!$user) {
            throw new HttpException(400, 'Invalid or expired reset token.');
        }

        if ($user->reset_token_expires_at && strtotime($user->reset_token_expires_at) < time()) {
            throw new HttpException(400, 'Reset token has expired.');
        }

        $user->update([
            'password' => password_hash($request->password, PASSWORD_BCRYPT),
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);

        return [
            'status' => 'ok',
            'message' => 'Password has been reset successfully.',
        ];
    }

    #[ApiDescription('Logout', 'Invalidates the current session.')]
    #[ApiStatus(200, 'Logged out')]
    public function logout(): array
    {
        $user = $_REQUEST['__auth_user'] ?? null;

        if ($user instanceof User) {
            $user->update(['token' => null]);
        }

        return [
            'status' => 'ok',
            'message' => 'Logged out successfully.',
        ];
    }

    #[ApiDescription('Get current user', 'Returns the authenticated user profile.')]
    #[ApiStatus(200, 'User profile')]
    #[ApiStatus(401, 'Not authenticated')]
    public function me(): array
    {
        $user = $_REQUEST['__auth_user'] ?? null;

        if (!$user instanceof User) {
            throw new HttpException(401, 'Not authenticated.');
        }

        $role = $user->role;

        return [
            'status' => 'ok',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role ? $role->name : null,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
            ],
        ];
    }
}
PHP;
    }

    private function controllerRole(): string
    {
        return <<<'PHP'
<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\RoleRequest;
use App\Dto\Auth\RoleResponse;
use App\Models\Permission;
use App\Models\Role;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\DB;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class RoleController
{
    #[ApiDescription('List all roles')]
    #[ApiStatus(200, 'Roles list')]
    public function index(): array
    {
        $roles = Role::query()->orderBy('name', 'ASC')->get();

        $data = array_map(function ($role) {
            $permissions = $role->permissions();
            $permissionNames = array_map(fn ($p) => $p->name, $permissions);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $permissionNames,
            ];
        }, $roles);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Show a role')]
    #[ApiStatus(200, 'Role found')]
    #[ApiStatus(404, 'Role not found')]
    public function show(string $id): array
    {
        $role = Role::findOrFail((int) $id);
        $permissions = $role->permissions();
        $permissionNames = array_map(fn ($p) => $p->name, $permissions);

        return [
            'status' => 'ok',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $permissionNames,
            ],
        ];
    }

    #[ApiDescription('Create a role')]
    #[ApiStatus(201, 'Role created')]
    #[ApiStatus(422, 'Validation error')]
    public function store(RoleRequest $request): array
    {
        // DTO validated automatically by Router injection

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $role->toArray(),
        ];
    }

    #[ApiDescription('Update a role')]
    #[ApiStatus(200, 'Role updated')]
    #[ApiStatus(404, 'Role not found')]
    #[ApiStatus(422, 'Validation error')]
    public function update(string $id, RoleRequest $request): array
    {
        // DTO validated automatically by Router injection

        $role = Role::findOrFail((int) $id);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $role->toArray(),
        ];
    }

    #[ApiDescription('Delete a role')]
    #[ApiStatus(200, 'Role deleted')]
    #[ApiStatus(404, 'Role not found')]
    public function delete(string $id): array
    {
        $role = Role::findOrFail((int) $id);
        $role->delete();

        return [
            'status' => 'ok',
            'message' => 'Role deleted.',
        ];
    }

    #[ApiDescription('Assign permissions to a role', 'Replaces all current permissions with the provided list.')]
    #[ApiStatus(200, 'Permissions assigned')]
    #[ApiStatus(404, 'Role not found')]
    public function assignPermissions(string $id): array
    {
        $role = Role::findOrFail((int) $id);

        $body = json_decode(file_get_contents('php://input'), true);
        $permissionIds = $body['permission_ids'] ?? [];

        if (!is_array($permissionIds)) {
            throw new HttpException(422, 'permission_ids must be an array.');
        }

        // Remove existing permissions
        DB::raw(
            'DELETE FROM role_permissions WHERE role_id = :role_id',
            ['role_id' => (int) $role->id]
        );

        // Insert new permissions
        foreach ($permissionIds as $permissionId) {
            $permission = Permission::find((int) $permissionId);
            if ($permission) {
                DB::raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)',
                    ['role_id' => (int) $role->id, 'permission_id' => (int) $permission->id]
                );
            }
        }

        $permissions = $role->permissions();
        $permissionNames = array_map(fn ($p) => $p->name, $permissions);

        return [
            'status' => 'ok',
            'message' => 'Permissions assigned to role.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $permissionNames,
            ],
        ];
    }
}
PHP;
    }

    private function controllerPermission(): string
    {
        return <<<'PHP'
<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\PermissionRequest;
use App\Dto\Auth\PermissionResponse;
use App\Models\Permission;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class PermissionController
{
    #[ApiDescription('List all permissions')]
    #[ApiStatus(200, 'Permissions list')]
    public function index(): array
    {
        $permissions = Permission::query()->orderBy('name', 'ASC')->get();

        $data = array_map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
        ], $permissions);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Show a permission')]
    #[ApiStatus(200, 'Permission found')]
    #[ApiStatus(404, 'Permission not found')]
    public function show(string $id): array
    {
        $permission = Permission::findOrFail((int) $id);

        return [
            'status' => 'ok',
            'data' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
            ],
        ];
    }

    #[ApiDescription('Create a permission')]
    #[ApiStatus(201, 'Permission created')]
    #[ApiStatus(422, 'Validation error')]
    public function store(PermissionRequest $request): array
    {
        // DTO validated automatically by Router injection

        $permission = Permission::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $permission->toArray(),
        ];
    }

    #[ApiDescription('Update a permission')]
    #[ApiStatus(200, 'Permission updated')]
    #[ApiStatus(404, 'Permission not found')]
    #[ApiStatus(422, 'Validation error')]
    public function update(string $id, PermissionRequest $request): array
    {
        // DTO validated automatically by Router injection

        $permission = Permission::findOrFail((int) $id);

        $permission->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $permission->toArray(),
        ];
    }

    #[ApiDescription('Delete a permission')]
    #[ApiStatus(200, 'Permission deleted')]
    #[ApiStatus(404, 'Permission not found')]
    public function delete(string $id): array
    {
        $permission = Permission::findOrFail((int) $id);
        $permission->delete();

        return [
            'status' => 'ok',
            'message' => 'Permission deleted.',
        ];
    }
}
PHP;
    }

    // ─── Middleware ────────────────────────────────────────────────

    private function createMiddleware(): void
    {
        $dir = "{$this->appDir}/Middleware";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/Auth.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Middleware Auth already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Middleware;

use App\Models\User;
use Fennec\Core\Container;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;

class Auth
{
    /**
     * Handle the authentication middleware.
     *
     * Usage in routes:
     *   [Auth::class, []]                          — just check authenticated
     *   [Auth::class, ['role:admin']]               — check user has 'admin' role
     *   [Auth::class, ['permission:users.create']]  — check user has 'users.create' permission
     *
     * The permission check follows the chain:
     *   user → user_roles → roles → role_permissions → permissions
     */
    public function handle(array $params = []): void
    {
        $rawToken = $this->extractToken();

        if (!$rawToken) {
            throw new HttpException(401, 'Authentication required.');
        }

        $user = $this->validateToken($rawToken);

        if (!$user) {
            throw new HttpException(401, 'Invalid or expired token.');
        }

        if (!$user->is_active) {
            throw new HttpException(403, 'Account is not active.');
        }

        // Store full User object in request for controllers
        $_REQUEST['__auth_user'] = $user;

        // Check role/permission constraints
        foreach ($params as $constraint) {
            if (str_starts_with($constraint, 'role:')) {
                $roleName = substr($constraint, 5);
                if (!$user->hasRole($roleName)) {
                    throw new HttpException(403, "Required role: {$roleName}.");
                }
            } elseif (str_starts_with($constraint, 'permission:')) {
                $permissionName = substr($constraint, 11);
                if (!$user->hasPermission($permissionName)) {
                    throw new HttpException(403, "Required permission: {$permissionName}.");
                }
            }
        }
    }

    /**
     * Get the currently authenticated user.
     */
    public static function user(): ?User
    {
        return $_REQUEST['__auth_user'] ?? null;
    }

    /**
     * Extract Bearer token from the Authorization header.
     */
    private function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate a JWT token using JwtService and return the associated user.
     */
    private function validateToken(string $rawToken): ?User
    {
        $container = Container::getInstance();
        $jwt = $container->get(JwtService::class);

        $claims = $jwt->decode($rawToken);

        if (!$claims || !isset($claims['sub'])) {
            return null;
        }

        $email = $claims['sub'];

        // Verify the token matches what is stored in DB
        $user = User::findByEmailAndToken($email, $rawToken);

        if (!$user) {
            return null;
        }

        return $user;
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Middleware/Auth.php';
    }

    // ─── Mail Templates ───────────────────────────────────────────

    private function createMailTemplate(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Mail/Auth";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Mail template {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Mail/Auth/{$name}.php";
    }

    private function mailAccountActivation(): string
    {
        return <<<'PHP'
<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class AccountActivation extends Mailable
{
    public string $templateName = 'account_activation';

    public function __construct(
        string $to,
        private string $name,
        private string $activationUrl,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'activation_url' => $this->activationUrl,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Activez votre compte';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Activate your account';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bonjour {{name}}</h1><p>Cliquez sur le lien ci-dessous pour activer votre compte :</p><p><a href="{{activation_url}}">Activer mon compte</a></p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Hello {{name}}</h1><p>Click the link below to activate your account:</p><p><a href="{{activation_url}}">Activate my account</a></p>';
    }
}
PHP;
    }

    private function mailPasswordReset(): string
    {
        return <<<'PHP'
<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class PasswordReset extends Mailable
{
    public string $templateName = 'password_reset';

    public function __construct(
        string $to,
        private string $name,
        private string $resetUrl,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'reset_url' => $this->resetUrl,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Reinitialisation de votre mot de passe';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Reset your password';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bonjour {{name}}</h1><p>Cliquez sur le lien ci-dessous pour reinitialiser votre mot de passe :</p><p><a href="{{reset_url}}">Reinitialiser mon mot de passe</a></p><p>Ce lien expire dans 1 heure.</p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Hello {{name}}</h1><p>Click the link below to reset your password:</p><p><a href="{{reset_url}}">Reset my password</a></p><p>This link expires in 1 hour.</p>';
    }
}
PHP;
    }

    private function mailWelcome(): string
    {
        return <<<'PHP'
<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class Welcome extends Mailable
{
    public string $templateName = 'welcome';

    public function __construct(
        string $to,
        private string $name,
        private string $service,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'service' => $this->service,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Bienvenue sur {{service}} !';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Welcome to {{service}}!';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bienvenue {{name}} !</h1><p>Votre compte sur {{service}} est maintenant actif.</p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Welcome {{name}}!</h1><p>Your account on {{service}} is now active.</p>';
    }
}
PHP;
    }

    // ─── Routes ───────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $dir = "{$this->appDir}/Routes";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/auth.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes auth.php already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\PermissionController;
use App\Controllers\Auth\RoleController;
use App\Middleware\Auth;

// ─── Public : Authentication ───────────────────────────────────
$router->group([
    'prefix' => '/auth',
    'description' => 'Authentication — Public routes',
], function ($router) {
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/activate/{token}', [AuthController::class, 'activate']);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ─── Authenticated : User routes ───────────────────────────────
$router->group([
    'prefix' => '/auth',
    'description' => 'Authentication — Authenticated routes',
    'middleware' => [[Auth::class, []]],
], function ($router) {
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/me', [AuthController::class, 'me']);
});

// ─── Admin only : Roles management ────────────────────────────
$router->group([
    'prefix' => '/auth/roles',
    'description' => 'Authentication — Roles management (admin)',
    'middleware' => [[Auth::class, ['role:admin']]],
], function ($router) {
    $router->get('', [RoleController::class, 'index']);
    $router->post('', [RoleController::class, 'store']);
    $router->get('/{id}', [RoleController::class, 'show']);
    $router->put('/{id}', [RoleController::class, 'update']);
    $router->delete('/{id}', [RoleController::class, 'delete']);
    $router->post('/{id}/permissions', [RoleController::class, 'assignPermissions']);
});

// ─── Admin only : Permissions management ──────────────────────
$router->group([
    'prefix' => '/auth/permissions',
    'description' => 'Authentication — Permissions management (admin)',
    'middleware' => [[Auth::class, ['role:admin']]],
], function ($router) {
    $router->get('', [PermissionController::class, 'index']);
    $router->post('', [PermissionController::class, 'store']);
    $router->get('/{id}', [PermissionController::class, 'show']);
    $router->put('/{id}', [PermissionController::class, 'update']);
    $router->delete('/{id}', [PermissionController::class, 'delete']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/auth.php';
    }

    // ─── Seeder ─────────────────────────────────────────────────────

    private function createSeeder(): void
    {
        $dir = FENNEC_BASE_PATH . '/database/seeders';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/AuthSeeder.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ AuthSeeder already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use Fennec\Core\DB;
use Fennec\Core\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::getInstance();
        $now = date('Y-m-d H:i:s');

        // Default roles
        $roles = [
            ['name' => 'admin', 'description' => 'Full access to all resources'],
            ['name' => 'manager', 'description' => 'Manage users and content'],
            ['name' => 'user', 'description' => 'Standard user access'],
        ];

        foreach ($roles as $role) {
            $db->raw(
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
                $db->raw(
                    'INSERT INTO permissions (name, description, created_at, updated_at) VALUES (:name, :description, :now, :now)',
                    ['name' => $name, 'description' => ucfirst($action) . ' ' . $resource, 'now' => $now]
                );
                // Get the inserted permission ID
                $stmt = $db->raw('SELECT id FROM permissions WHERE name = :name', ['name' => $name]);
                $permissionIds[$name] = $stmt->fetchColumn();
            }
        }

        // Get role IDs
        $adminId = $db->raw('SELECT id FROM roles WHERE name = :name', ['name' => 'admin'])->fetchColumn();
        $managerId = $db->raw('SELECT id FROM roles WHERE name = :name', ['name' => 'manager'])->fetchColumn();
        $userId = $db->raw('SELECT id FROM roles WHERE name = :name', ['name' => 'user'])->fetchColumn();

        // Admin gets ALL permissions
        foreach ($permissionIds as $permId) {
            $db->raw(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                ['rid' => $adminId, 'pid' => $permId]
            );
        }

        // Manager gets read + update on users and organizations, full on roles
        $managerPerms = ['users.read', 'users.update', 'organizations.create', 'organizations.read', 'organizations.update', 'organizations.delete'];
        foreach ($managerPerms as $perm) {
            if (isset($permissionIds[$perm])) {
                $db->raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                    ['rid' => $managerId, 'pid' => $permissionIds[$perm]]
                );
            }
        }

        // User gets read-only on users and organizations
        $userPerms = ['users.read', 'organizations.read'];
        foreach ($userPerms as $perm) {
            if (isset($permissionIds[$perm])) {
                $db->raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                    ['rid' => $userId, 'pid' => $permissionIds[$perm]]
                );
            }
        }

        // Create default admin user
        $db->raw(
            'INSERT INTO users (name, email, password, is_active, activated_at, created_at, updated_at) VALUES (:name, :email, :password, 1, :now, :now, :now)',
            [
                'name' => 'Admin',
                'email' => 'admin@fennectra.dev',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'now' => $now,
            ]
        );

        // Assign admin role to admin user via user_roles pivot
        $adminUserId = $db->raw('SELECT id FROM users WHERE email = :email', ['email' => 'admin@fennectra.dev'])->fetchColumn();
        $db->raw(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $adminUserId, 'rid' => $adminId]
        );

        echo "  AuthSeeder: 3 roles, " . count($permissionIds) . " permissions, 1 admin user created.\n";
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'database/seeders/AuthSeeder.php';
    }
}
