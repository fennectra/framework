<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Response;
use Fennec\Core\Router;

class UiSystemController
{
    use UiHelper;

    public function info(): void
    {
        Response::json([
            'php' => PHP_VERSION,
            'os' => PHP_OS,
            'sapi' => php_sapi_name(),
            'frankenphp' => isset($_SERVER['FRANKENPHP_WORKER']),
            'extensions' => get_loaded_extensions(),
            'timezone' => date_default_timezone_get(),
            'maxUpload' => ini_get('upload_max_filesize'),
            'maxPost' => ini_get('post_max_size'),
            'memoryLimit' => ini_get('memory_limit'),
            'maxExecution' => ini_get('max_execution_time'),
        ]);
    }

    public function modules(): void
    {
        $modules = [
            'jwt' => [
                'enabled' => (bool) Env::get('JWT_SECRET'),
                'label' => 'JWT Authentication',
                'category' => 'auth',
            ],
            'orm' => [
                'enabled' => true,
                'label' => 'ORM / Query Builder',
                'category' => 'core',
            ],
            'events' => [
                'enabled' => true,
                'label' => 'Event System',
                'driver' => Env::get('EVENT_BROKER') ?: 'sync',
                'category' => 'core',
            ],
            'worker' => [
                'enabled' => isset($_SERVER['FRANKENPHP_WORKER']),
                'label' => 'FrankenPHP Worker',
                'category' => 'runtime',
            ],
            'profiler' => [
                'enabled' => (bool) Env::get('PROFILER_ENABLED'),
                'label' => 'Request Profiler',
                'category' => 'dev',
            ],
            'scheduler' => [
                'enabled' => (bool) Env::get('SCHEDULER_ENABLED'),
                'label' => 'Task Scheduler',
                'category' => 'core',
            ],
            'queue' => [
                'enabled' => (bool) Env::get('QUEUE_DRIVER'),
                'driver' => Env::get('QUEUE_DRIVER') ?: 'none',
                'label' => 'Job Queue',
                'category' => 'core',
            ],
            'notifications' => [
                'enabled' => true,
                'label' => 'Notifications',
                'category' => 'core',
            ],
            'webhooks' => [
                'enabled' => $this->tableExists('webhooks'),
                'label' => 'Webhooks',
                'category' => 'integration',
            ],
            'images' => [
                'enabled' => extension_loaded('gd'),
                'label' => 'Image Processing (GD)',
                'category' => 'media',
            ],
            'feature_flags' => [
                'enabled' => $this->tableExists('feature_flags'),
                'label' => 'Feature Flags',
                'category' => 'core',
            ],
            'multi_tenant' => [
                'enabled' => file_exists((defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/app/config/tenants.php'),
                'label' => 'Multi-Tenant',
                'category' => 'saas',
            ],
            'storage' => [
                'enabled' => true,
                'driver' => Env::get('STORAGE_DRIVER') ?: 'local',
                'label' => 'File Storage',
                'category' => 'core',
            ],
            'pdf' => [
                'enabled' => false,
                'label' => 'PDF Generation',
                'category' => 'media',
            ],
            'soc2' => [
                'enabled' => $this->tableExists('audit_logs'),
                'label' => 'SOC 2 Audit Trail',
                'category' => 'compliance',
            ],
            'iso27001' => [
                'enabled' => (bool) Env::get('JWT_SECRET'),
                'label' => 'ISO 27001 Security',
                'category' => 'compliance',
            ],
            'nf525' => [
                'enabled' => $this->tableExists('nf525_closings'),
                'label' => 'NF 525 French Tax',
                'category' => 'compliance',
            ],
            'gdpr' => [
                'enabled' => $this->tableExists('consent_objects'),
                'label' => 'GDPR Compliance',
                'category' => 'compliance',
            ],
            'oauth' => [
                'enabled' => (bool) (Env::get('GOOGLE_CLIENT_ID') || Env::get('GITHUB_CLIENT_ID')),
                'label' => 'OAuth Providers',
                'category' => 'auth',
            ],
            'broadcasting' => [
                'enabled' => extension_loaded('redis'),
                'label' => 'Broadcasting (SSE)',
                'category' => 'realtime',
            ],
            'mail' => [
                'enabled' => (bool) Env::get('MAIL_HOST'),
                'label' => 'Email / SMTP',
                'category' => 'integration',
            ],
            'cache' => [
                'enabled' => true,
                'driver' => Env::get('CACHE_DRIVER') ?: 'file',
                'label' => 'Cache',
                'category' => 'core',
            ],
        ];

        // Database drivers
        $dbDriver = Env::get('DB_DRIVER') ?: 'pgsql';
        $modules['postgresql'] = ['enabled' => $dbDriver === 'pgsql', 'label' => 'PostgreSQL', 'category' => 'database'];
        $modules['mysql'] = ['enabled' => $dbDriver === 'mysql', 'label' => 'MySQL', 'category' => 'database'];
        $modules['sqlite'] = ['enabled' => $dbDriver === 'sqlite', 'label' => 'SQLite', 'category' => 'database'];

        Response::json($modules);
    }

    public function routes(): void
    {
        try {
            $reflection = new \ReflectionClass(Router::class);
            $routesProp = $reflection->getProperty('routes');
            $routesProp->setAccessible(true);

            // Get router instance from App container
            $router = \Fennec\Core\Container::getInstance()->get(Router::class);
            $routes = $routesProp->getValue($router);

            $result = [];
            foreach ($routes as $method => $methodRoutes) {
                foreach ($methodRoutes as $pattern => $config) {
                    $result[] = [
                        'method' => strtoupper($method),
                        'pattern' => $pattern,
                        'controller' => is_array($config['handler'] ?? null) ? implode('::', $config['handler']) : ($config['handler'] ?? 'closure'),
                        'middleware' => $config['middleware'] ?? [],
                        'description' => $config['description'] ?? '',
                    ];
                }
            }

            usort($result, fn ($a, $b) => strcmp($a['pattern'], $b['pattern']));

            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function database(): void
    {
        $driver = Env::get('DB_DRIVER') ?: 'pgsql';

        $info = [
            'driver' => $driver,
            'host' => Env::get('DB_HOST') ?: 'localhost',
            'port' => Env::get('DB_PORT') ?: '5432',
            'database' => Env::get('DB_NAME') ?: '',
        ];

        try {
            $tables = [];

            if ($driver === 'pgsql') {
                $rows = DB::raw(
                    "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
                )->fetchAll();
                $tables = array_column($rows, 'tablename');
            } elseif ($driver === 'mysql') {
                $rows = DB::raw('SHOW TABLES')->fetchAll();
                $tables = array_map(fn ($r) => array_values($r)[0], $rows);
            } elseif ($driver === 'sqlite') {
                $rows = DB::raw(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                )->fetchAll();
                $tables = array_column($rows, 'name');
            }

            $info['tables'] = $tables;
            $info['tableCount'] = count($tables);
        } catch (\Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        Response::json($info);
    }

    public function tableInfo(string $name): void
    {
        $driver = Env::get('DB_DRIVER') ?: 'pgsql';

        try {
            $columns = [];

            if ($driver === 'pgsql') {
                $columns = DB::raw(
                    "SELECT column_name, data_type, is_nullable, column_default
                     FROM information_schema.columns
                     WHERE table_name = ?
                     ORDER BY ordinal_position",
                    [$name]
                )->fetchAll();
            } elseif ($driver === 'mysql') {
                $columns = DB::raw("DESCRIBE {$name}")->fetchAll();
            } elseif ($driver === 'sqlite') {
                $columns = DB::raw("PRAGMA table_info('{$name}')")->fetchAll();
            }

            $count = DB::raw("SELECT COUNT(*) as cnt FROM {$name}")->fetchAll()[0]['cnt'] ?? 0;

            Response::json([
                'name' => $name,
                'columns' => $columns,
                'rowCount' => (int) $count,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function env(): void
    {
        // Only expose safe, non-secret env vars
        $safe = [
            'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_LOCALE',
            'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME',
            'CACHE_DRIVER', 'QUEUE_DRIVER', 'STORAGE_DRIVER', 'EVENT_BROKER',
            'PROFILER_ENABLED', 'SCHEDULER_ENABLED',
            'MAIL_HOST', 'MAIL_PORT', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'AUDIT_RETENTION_DAYS',
            'UI_ADMIN_EMAIL',
        ];

        $result = [];
        foreach ($safe as $key) {
            $value = Env::get($key);
            if ($value !== '') {
                $result[$key] = $value;
            }
        }

        Response::json($result);
    }

    public function logs(): void
    {
        $type = $this->queryString('type', 'app');
        $limit = $this->queryInt('limit', 100);

        $logDir = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/var/logs';

        $pattern = match ($type) {
            'security' => '/security-*.log',
            'error' => '/error-*.log',
            default => '/app-*.log',
        };

        $logFiles = glob($logDir . $pattern);
        if ($logFiles === false) {
            $logFiles = [];
        }
        rsort($logFiles);
        $logFiles = array_slice($logFiles, 0, 3);

        $lines = [];
        foreach ($logFiles as $file) {
            $content = array_filter(file($file, FILE_IGNORE_NEW_LINES));
            foreach (array_reverse($content) as $line) {
                $lines[] = $line;
                if (count($lines) >= $limit) {
                    break 2;
                }
            }
        }

        Response::json([
            'type' => $type,
            'lines' => $lines,
            'total' => count($lines),
        ]);
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::raw("SELECT 1 FROM {$table} LIMIT 1");

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
