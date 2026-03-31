<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="fennec_logo.png">
    <source media="(prefers-color-scheme: light)" srcset="fennec_logo.png">
    <img alt="Fennectra Framework" src="fennec_logo.png" width="600">
  </picture>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3+-8892BF?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PHPStan-level%205-brightgreen?logo=php" alt="PHPStan Level 5">
  <img src="https://img.shields.io/badge/PHPUnit-285%2B%20tests-brightgreen?logo=php" alt="PHPUnit 285+ tests">
  <img src="https://img.shields.io/badge/DB-PostgreSQL%20%7C%20MySQL%20%7C%20SQLite-336791?logo=postgresql&logoColor=white" alt="Multi-DB">
  <img src="https://img.shields.io/badge/FrankenPHP-worker-blueviolet?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0xMiAyQTEwIDEwIDAgMCAyIDIgMTJhMTAgMTAgMCAwIDAgMTAgMTAgMTAgMTAgMCAwIDAgMTAtMTBBMTAgMTAgMCAwIDAgMTIgMnoiLz48L3N2Zz4=" alt="FrankenPHP">
  <img src="https://img.shields.io/badge/Deploy-GKE-4285F4?logo=googlecloud&logoColor=white" alt="GKE">
  <img src="https://img.shields.io/badge/SOC_2-Compliant-00B140?logo=securityscorecard&logoColor=white" alt="SOC 2 Compliant">
  <img src="https://img.shields.io/badge/ISO_27001-Ready-0052CC?logo=iso&logoColor=white" alt="ISO 27001">
  <img src="https://img.shields.io/badge/NF525-Certifiable-FF6600?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0xNCAySDZjLTEuMSAwLTIgLjktMiAydjE2YzAgMS4xLjkgMiAyIDJoMTJjMS4xIDAgMi0uOSAyLTJWOGwtNi02em00IDE4SDZWNGg3djVoNXYxMXoiLz48L3N2Zz4=&logoColor=white" alt="NF525">
  <img src="https://img.shields.io/badge/GDPR-Compliant-00B140?logo=shieldsdotio&logoColor=white" alt="GDPR">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Multi--tenant-Ready-6A1B9A?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0xMiA3VjNINnYxOGgxMlY3aC02em0tMiAxMkg4di0yaDJ2MnptMC00SDh2LTJoMnYyem0wLTRIOFY5aDJ2MnptNiA4aC0ydi0yaDJ2MnptMC00aC0ydi0yaDJ2MnptMC00aC0yVjloMnYyem0wLTRoLTJWNWgydjJ6Ii8+PC9zdmc+&logoColor=white" alt="Multi-tenant">
  <img src="https://img.shields.io/badge/Webhooks-HMAC--SHA256-E65100?logo=webhook&logoColor=white" alt="Webhooks">
  <img src="https://img.shields.io/badge/Image_Transforms-Intervention-FF6F00?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0yMSAxOUg1VjNINHYxN0gyMXYxek0xOCA5SDhsNCA1IDQtNXoiLz48L3N2Zz4=&logoColor=white" alt="Image Transforms">
  <img src="https://img.shields.io/badge/S3-Object%20Storage-E47911?logo=amazons3&logoColor=white" alt="S3 Object Storage">
  <img src="https://img.shields.io/badge/GCS-Cloud%20Storage-4285F4?logo=googlecloud&logoColor=white" alt="GCS Cloud Storage">
</p>

<p align="center">
  <strong>High-performance PHP 8.3+ framework</strong><br>
  JWT · ORM · Events · Worker · Profiler · Scheduler · Queue · Notifications · Webhooks · Images · Feature Flags · Multi-tenant · Storage · PDF · OAuth · OIDC · SAML · SOC 2 · ISO 27001 · NF525 · GDPR · PostgreSQL · MySQL · SQLite
</p>

---

> High-performance PHP 8.3+ framework with Dependency Injection, JWT, auto-generated OpenAPI, CLI, ORM with eager loading, **Multi-database** (PostgreSQL, MySQL, SQLite), Event Dispatcher with multiple brokers, Profiler, Rate Limiting, Migrations, K8s-safe Scheduler, Job Queue, Feature Flags, multi-channel Notifications, **HMAC-SHA256 signed Webhooks**, **Image Transforms** (Intervention/Image), SSE Broadcasting, OAuth2/OIDC/SAML 2.0 SSO, multi-driver Storage (Local, S3, GCS), PDF generation, **GDPR** (consent, DPO dashboard, data subject rights), and FrankenPHP worker support.

---

## Quickstart

```bash
# Install the CLI (once)
composer global require fennectra/installer

# Create a new project
fennectra new my-api
cd my-api
cp .env.example .env        # configure DB_DRIVER + credentials + SECRET_KEY
./forge serve               # http://localhost:8080
```

---

## Architecture

```
┌───────────────────────────────────────────────────────────────┐
│                      public/index.php                         │
├───────────────────────────────────────────────────────────────┤
│  Request → CORS → Tenant → Profiler → Logging → Auth → Ctrl  │
├─────────┬─────────┬─────────┬──────────┬─────────┬───────────┤
│  Router │   DI    │  ORM    │  Events  │  JWT    │   Rate    │
│         │Container│  Model  │Dispatcher│ Service │  Limiter  │
├─────────┼─────────┼─────────┼──────────┼─────────┼───────────┤
│ Cache   │Scheduler│  Queue  │ Feature  │  State  │Notificat. │
│ Redis   │+RedLock │  Jobs   │  Flags   │ Machine │Multi-chan │
├─────────┼─────────┼─────────┼──────────┼─────────┼───────────┤
│Webhooks │  Image  │ Storage │   SSE    │OAuth/SSO│    PDF    │
│HMAC-sign│Transform│L/S3/GCS │Broadcast │OIDC+SAML│  dompdf   │
├─────────┼─────────┼─────────┼──────────┼─────────┼───────────┤
│ Audit   │Encrypti.│ Security│  NF525   │  GDPR   │           │
│ SOC 2   │AES-256  │ Logger  │ Fiscal   │ Consent │           │
├─────────┴─────────┴─────────┴──────────┴─────────┴───────────┤
│          src/Core/ — The engine                               │
├───────────────────────────────────────────────────────────────┤
│      PostgreSQL / MySQL / SQLite        Redis                 │
└───────────────────────────────────────────────────────────────┘
```

```
framework/              ← framework core (Packagist: fennectra/framework) (do not modify)
  src/
    Attributes/         ← validation, API docs, ORM, RateLimit, StateMachine, Broadcast, Auditable, Encrypted, Nf525
    Commands/           ← CLI (serve, make:*, quality, migrate, seed, queue, schedule, deploy, tinker)
    Core/               ← App, Router, Container, ORM, Events, JWT...
      Database/         ← DB drivers (PostgreSQL, MySQL, SQLite) + DriverFactory
      Profiler/         ← per-request debug profiler
      Relations/        ← eager loading (BelongsTo, HasMany, HasOne)
      RateLimiter/      ← rate limiting (Redis + InMemory stores)
      Redis/            ← RedisConnection, RedisLock
      Cache/            ← RedisCache, TaggedCache
      Migration/        ← MigrationRunner, Seeder, FakeDataGenerator
      Scheduler/        ← Schedule, CronExpression, Redis Lock K8s-safe
      Queue/            ← Job dispatch, QueueWorker, FailedJobHandler
      Feature/          ← Feature Flags with Redis cache
      StateMachine/     ← controlled transitions on Models
      Notification/     ← multi-channel (Mail, Slack, Database, Webhook)
      Webhook/          ← outgoing HMAC-SHA256 webhooks + delivery jobs
      Image/            ← image transformations (GD-based)
      Broadcasting/     ← SSE via Redis Pub/Sub
      OAuth/            ← Google, GitHub, OIDC (OpenID Connect) providers
      Saml/             ← SAML 2.0 Service Provider (enterprise SSO)
      Audit/            ← HasAuditTrail (SOC 2)
      Encryption/       ← AES-256-GCM at rest (SOC 2)
      Security/         ← SecurityLogger, PasswordPolicy, AccountLockout (ISO 27001)
      Logging/          ← LogMaskingProcessor (SOC 2)
      Nf525/            ← HasNf525, ClosingService, FecExporter, HashChainVerifier
    Middleware/         ← Auth, CORS, Profiler, RateLimit, Security, Logging, IpAllowlist
  database/             ← migrations and seeders
  config/               ← phpstan, phpunit, cs-fixer
  docker/               ← Dockerfile, docker-compose, Caddyfile, kubernetes

app/                    ← your application code
  Controllers/          ← HTTP handlers
  Models/               ← ORM models
  Dto/                  ← input/output validation
  Routes/               ← route files (auto-loaded)
  Jobs/                 ← job classes for the queue
  config/tenants.php    ← multi-tenancy mapping (domain/port → database)
  Schedule.php          ← scheduled tasks

tests/                  ← application tests
  TestCase.php          ← base test class (DB helpers)
  Unit/                 ← unit tests (pure logic)
  Feature/              ← feature tests (with DB)

storage/                ← uploaded files (local driver)

public/                 ← web root
  index.php             ← HTTP entry point (worker + classic)
  router.php            ← router script for the built-in PHP server
  storage               ← symlink to storage/ (created by storage:link)
```

---

<details>
<summary><strong>Routing</strong></summary>

Routes are defined in `app/Routes/` — one file per domain, loaded automatically.

```php
// app/Routes/admin.php
$router->group([
    'prefix' => '/admin',
    'description' => 'Administration',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('/users', [AdminController::class, 'listUsers']);
    $router->post('/users', [AdminController::class, 'create']);
    $router->put('/users/{id}', [AdminController::class, 'update']);
    $router->delete('/users/{id}', [AdminController::class, 'delete']);
});
```

**Available methods:** `get()`, `post()`, `put()`, `delete()`
**Dynamic parameters:** `/users/{id}` — automatically injected into the controller
**Middleware:** per route or per group
**OpenAPI:** auto-generated documentation from attributes

</details>

<details>
<summary><strong>ORM & Query Builder</strong></summary>

### ORM Model

```php
#[Table('users')]
class User extends Model
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
```

### Fluent Queries

```php
// Search
User::where('active', true)
    ->where('role_id', '>', 5)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();                    // Collection of Models

User::find(123);               // or null
User::findOrFail(123);         // or HttpException 404

// Create
$user = new User(['email' => 'x@y.com', 'name' => 'Ali']);
$user->save();
// or
User::create(['email' => 'x@y.com']);

// Update
$user->email = 'new@y.com';
$user->save();                 // UPDATE only modified fields

// Delete
$user->delete();               // soft delete (deleted_at)
$user->forceDelete();          // actual DELETE
$user->restore();              // undo soft delete
```

### Relations

| Method        | Type         | Example                      |
|---------------|-------------|------------------------------|
| `belongsTo()` | Many-to-One | User → Role                  |
| `hasMany()`   | One-to-Many | Role → Users                 |
| `hasOne()`    | One-to-One  | User → Profile               |

### Eager Loading (N+1 prevention)

```php
// BEFORE: N+1 queries (1 + N queries)
$users = User::where('active', true)->get();
foreach ($users as $user) {
    echo $user->role->name;  // 1 query per user!
}

// AFTER: 2 queries total
$users = User::with('role')->where('active', true)->get();
foreach ($users as $user) {
    echo $user->role->name;  // already loaded, 0 queries
}

// Multiple relations
User::with('role', 'profile')->paginate(20);
```

### Query Builder (without ORM)

```php
DB::table('users')->where('active', true)->get();        // main database
DB::table('clients', 'job')->limit(10)->get();           // secondary database
DB::raw('SELECT * FROM users WHERE id = :id', ['id' => 1]);
DB::transaction(function () { /* ... */ });
```

</details>

<details>
<summary><strong>JWT Authentication & RBAC</strong></summary>

### Token Generation

```php
// POST /token — generate a JWT
$jwt = $jwtService->generate([
    'email' => $user->email,
    'role'  => $user->role()->name,
    'id'    => $user->id,
]);
```

### Route Protection

```php
// Accessible to all authenticated users
$router->get('/profile', [UserController::class, 'me'], [[Auth::class]]);

// Restricted to admins
$router->get('/admin', [AdminController::class, 'index'], [[Auth::class, ['admin']]]);

// Restricted to admin + manager
$router->group([
    'middleware' => [[Auth::class, ['admin', 'manager']]],
], function ($router) { /* ... */ });
```

### Get the Authenticated User

```php
$user = Auth::user();  // ['email' => ..., 'role' => ..., 'id' => ...]
```

**Tokens:** Access (15min) + Refresh (24h) — configurable via `JWT_ACCESS_TTL` and `JWT_REFRESH_TTL`

</details>

<details>
<summary><strong>Event Dispatcher</strong></summary>

Event system with 3 interchangeable brokers via `EVENT_BROKER`:

| Broker     | Transport         | Dependency      | Usage            |
|-----------|-------------------|----------------|-----------------|
| `sync`    | Same process      | None            | Dev / default    |
| `redis`   | Redis Pub/Sub     | `REDIS_*`       | Async production |
| `database`| PostgreSQL table  | `EVENT_DB_*`    | Async without Redis |

### Usage

```php
// Dispatch an event
Event::dispatch('user.created', $userData);

// Listen
Event::listen('user.created', function ($data) {
    // send email, log, notify...
}, priority: 10);

// Listen once
Event::once('user.verified', fn($data) => /* ... */);

// Check if listeners exist
Event::hasListeners('user.created');  // bool
```

</details>

<details>
<summary><strong>DTOs & Validation</strong></summary>

Validation via PHP 8.1+ attributes — auto-documented in OpenAPI.

```php
readonly class ProductRequest
{
    public function __construct(
        #[Required]
        #[MinLength(3)]
        #[Description('Product name')]
        public string $name,

        #[Required]
        #[Email]
        public string $contact_email,

        #[Min(0)]
        public float $price,
    ) {}
}
```

### Available Attributes

| Attribute       | Description                     |
|----------------|---------------------------------|
| `#[Required]`  | Required field                  |
| `#[Email]`     | Valid email                     |
| `#[MinLength]` | Minimum length                  |
| `#[MaxLength]` | Maximum length                  |
| `#[Min]`       | Minimum numeric value           |
| `#[Max]`       | Maximum numeric value           |
| `#[Regex]`     | Custom regex pattern            |
| `#[ArrayOf]`   | Array element typing            |
| `#[Description]` | Field documentation           |

```php
$errors = Validator::validate(ProductRequest::class, $requestData);
```

</details>

<details>
<summary><strong>CLI — Commands</strong></summary>

```bash
./forge                              # list all commands
./forge serve                        # PHP dev server
./forge serve --frankenphp           # native FrankenPHP
./forge serve --frankenphp --worker  # worker mode (max perf)
./forge serve --port=3000            # custom port
```

### CRUD Generation

```bash
./forge make:all Product --roles=admin,manager     # full CRUD
./forge make:all Invoice --connection=job           # on secondary database
./forge make:all Article --no-auth                  # without auth
```

### Individual Generation

```bash
./forge make:model Product
./forge make:controller ProductController --crud
./forge make:dto ProductRequest --request
./forge make:dto ProductResponse --response
./forge make:route Product --prefix=/product --middleware=auth
./forge make:event UserCreated
./forge make:listener SendWelcomeEmail
./forge make:test ProductService                 # unit test
./forge make:test Auth/Login --feature           # feature test
```

### Migrations & Seeding

```bash
./forge migrate                      # apply migrations
./forge migrate --rollback           # rollback the last batch
./forge migrate --status             # view current status
./forge make:migration add_phone     # create a migration
./forge make:audit                   # full audit module (SOC 2)
./forge make:webhook                 # full webhooks module
./forge make:nf525                   # full NF525 module (fiscal)
./forge make:rgpd                    # full GDPR module (consent)
./forge db:seed                      # run seeders
./forge make:seeder UserSeeder       # create a seeder
```

### Queue & Scheduler

```bash
./forge queue:work                   # consume jobs
./forge queue:work --queue=emails    # specific queue
./forge queue:retry --id=5           # retry a failed job
./forge schedule:run                 # run due tasks
./forge make:job SendWelcomeEmail    # create a job
```

### Feature Flags & Deploy

```bash
./forge feature list                 # list flags
./forge feature enable dark-mode     # enable a flag
./forge feature disable dark-mode    # disable a flag
./forge deploy                       # build + push + K8s rollout
./forge deploy --dry-run             # preview without executing
```

### Tinker (Interactive SQL)

```bash
./forge tinker --sql="SELECT * FROM users LIMIT 5"
./forge tinker --sql="\dt"                         # list tables
./forge tinker --sql="\d users"                    # describe table
./forge tinker --sql="SELECT 1" --connection=job   # secondary database
```

### Storage

```bash
./forge storage:link                 # symlink public/storage → storage/
```

### Testing

```bash
./forge test                         # run all tests
./forge test --unit                  # unit tests only
./forge test --feature               # feature tests only
./forge test --filter=UserService    # specific test
./forge test --coverage              # with code coverage
./forge make:test UserService        # create a unit test
./forge make:test Auth/Login --feature  # create a feature test
```

### Quality & Cache

```bash
./forge quality                      # lint + PHPStan + tests
./forge quality --fix                # auto-fix style
./forge cache:clear                  # clear cache
./forge cache:routes                 # cache routes
```

</details>

<details>
<summary><strong>FrankenPHP Worker</strong></summary>

High-performance mode where the application boots **once** and handles requests in a loop.

```
┌─────────────────────────────────────────┐
│           FrankenPHP Worker             │
│                                         │
│   Boot (1x) ──→ ┌───────────────────┐  │
│                  │  Request Loop     │  │
│                  │  req → handle     │  │
│                  │  req → handle     │  │
│                  │  req → handle     │  │
│                  │  ...              │  │
│                  └───────────────────┘  │
│                                         │
│   10-100x faster than PHP-FPM           │
└─────────────────────────────────────────┘
```

### Starting

```bash
# Native dev
./forge serve --frankenphp --worker

# Docker (uses Caddyfile + frankenphp run)
docker build -f Dockerfile.frankenphp -t php-api:franken .
docker run -p 8080:8080 --env-file .env php-api:franken
```

### Monitoring & Probes

```
GET /health          → basic health check
GET /healthz         → liveness probe (K8s)
GET /readyz          → readiness probe (DB + Redis)
GET /debug/worker    → worker stats (requests, memory, trend)
GET /debug/profiler  → profiler (last 50 requests with SQL, events, timing)
```

- `index.php` automatically detects worker mode via `frankenphp_handle_request()`
- Caddyfile for worker routing (not `php-server`)
- Monolog logger (14-day rotation, stderr for Docker/K8s)
- WorkerStats: memory delta, trend analysis (stable/growing/spiky), error tracking
- Guaranteed cleanup (try/finally): DB flush, auth reset, GC
- Configurable request limit (`MAX_REQUESTS`)

</details>

<details>
<summary><strong>Multi-database & Multi-driver</strong></summary>

### Supported Drivers

The framework supports **3 database drivers** via the `DB_DRIVER` variable:

| Driver | `DB_DRIVER` | Env prefix | Default port |
|--------|-------------|------------|-----------------|
| PostgreSQL | `pgsql` (default) | `POSTGRES_` | 5432 |
| MySQL | `mysql` | `MYSQL_` | 3306 |
| SQLite | `sqlite` | `SQLITE_` | — |

### PostgreSQL Configuration (default)

```env
DB_DRIVER=pgsql

POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DB=fennectra
POSTGRES_USER=fennectra
POSTGRES_PASSWORD=secret
```

### MySQL Configuration

```env
DB_DRIVER=mysql

MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DB=myapp
MYSQL_USER=root
MYSQL_PASSWORD=secret
```

### SQLite Configuration

```env
DB_DRIVER=sqlite

SQLITE_DB=var/database.sqlite
# or in-memory for tests:
# SQLITE_DB=:memory:
```

### Multiple Connections

```env
# Secondary database (any name)
POSTGRES_JOB_HOST=10.0.0.50
POSTGRES_JOB_DB=job_database
POSTGRES_JOB_USER=user
POSTGRES_JOB_PASSWORD=pass
```

```php
DB::table('users')->get();                     // main database
DB::table('invoices', 'job')->get();           // "job" database
DB::table('test_data', 'test')->get();         // "test" database
```

### Custom Driver

```php
use Fennec\Core\Database\DriverFactory;

// Register a custom driver (CockroachDB, etc.)
DriverFactory::register('cockroach', MyCockroachDriver::class);
```

Models generated with `--connection=job` automatically use the correct connection.

</details>

<details>
<summary><strong>Multi-tenancy</strong></summary>

Database isolation per tenant, automatically resolved from the HTTP **domain** or **port**.

### Configuration

**1. Declare tenants in `app/config/tenants.php`:**

```php
return [
    'domains' => [
        'client1.example.com' => 'client1',
        'client2.example.com' => 'client2',
        '*.client3.com'       => 'client3',   // wildcard subdomains
    ],

    'ports' => [
        8081 => 'client1',   // useful for local dev
        8082 => 'client2',
    ],

    'tenants' => [
        'client1' => [
            'host'     => 'POSTGRES_TENANT_CLIENT1_HOST',
            'port'     => 'POSTGRES_TENANT_CLIENT1_PORT',
            'db'       => 'POSTGRES_TENANT_CLIENT1_DB',
            'user'     => 'POSTGRES_TENANT_CLIENT1_USER',
            'password' => 'POSTGRES_TENANT_CLIENT1_PASSWORD',
        ],
        'client2' => [
            'host'     => 'POSTGRES_TENANT_CLIENT2_HOST',
            'port'     => 'POSTGRES_TENANT_CLIENT2_PORT',
            'db'       => 'POSTGRES_TENANT_CLIENT2_DB',
            'user'     => 'POSTGRES_TENANT_CLIENT2_USER',
            'password' => 'POSTGRES_TENANT_CLIENT2_PASSWORD',
        ],
    ],
];
```

**2. Add environment variables in `.env`:**

```env
POSTGRES_TENANT_CLIENT1_HOST=localhost
POSTGRES_TENANT_CLIENT1_PORT=5432
POSTGRES_TENANT_CLIENT1_DB=client1_db
POSTGRES_TENANT_CLIENT1_USER=client1
POSTGRES_TENANT_CLIENT1_PASSWORD=secret

POSTGRES_TENANT_CLIENT2_HOST=10.0.0.50
POSTGRES_TENANT_CLIENT2_PORT=5432
POSTGRES_TENANT_CLIENT2_DB=client2_db
POSTGRES_TENANT_CLIENT2_USER=client2
POSTGRES_TENANT_CLIENT2_PASSWORD=secret
```

### How it Works

- The `TenantMiddleware` detects the tenant on each request (domain > wildcard > port)
- The `default` connection is automatically redirected to the tenant's database
- Named connections (`job`, `test`, etc.) are **not** affected
- Compatible with worker mode: the tenant is reset between each request
- If no tenant matches and multi-tenancy is configured, a 400 error is returned

### Resolution Priority

1. Exact domain (`client1.example.com`)
2. Wildcard (`*.client3.com`)
3. Port (`8081`)

### Accessing the Current Tenant

```php
// In a controller (via the Container)
$tenantManager = Container::getInstance()->get(TenantManager::class);
$tenantManager->current();         // 'client1' or null

// In a middleware (via request attributes)
$tenantId = $request->getAttribute('tenant');
```

### Local Multi-tenant Development

```bash
# Start 2 instances on different ports
./forge serve --port=8081   # → client1
./forge serve --port=8082   # → client2
```

</details>

<details>
<summary><strong>Dependency Injection Container</strong></summary>

```php
// Register a singleton
$container->singleton(JwtService::class, fn() => new JwtService($secret));

// Resolve automatically
$jwt = $container->get(JwtService::class);

// Factory (new instance on each call)
$container->bind(Logger::class, fn() => new Logger('app'));
```

Automatic resolution of constructor dependencies.
The Container is accessible via `$app->container()` or `Container::getInstance()`.

</details>

<details>
<summary><strong>Auto-generated API Documentation</strong></summary>

OpenAPI documentation automatically generated from code — **routes registered automatically by the framework**.

- **Scalar UI:** [http://localhost:8080/docs](http://localhost:8080/docs)
- **OpenAPI JSON:** [http://localhost:8080/docs/openapi](http://localhost:8080/docs/openapi)

Automatic introspection:
- Routes and HTTP methods
- `#[ApiDescription]`, `#[ApiStatus]` attributes
- DTO schemas (fields, types, validation)
- Required roles and authentication
- **Two-level sidebar menu** via `x-tagGroups` (based on URL segments)

```env
DOCS_ENABLED=true          # enable in production (auto in dev)
DOCS_PREFIX=/api-docs      # custom URL prefix (default: /docs)
```

### Sidebar grouping

Routes are automatically grouped into a **two-level collapsible menu** based on URL structure:

```
/app/users/...   → Group "App" → Tag "App/Users"
/app/roles/...   → Group "App" → Tag "App/Roles"
/auth/login/...  → Group "Auth" → Tag "Auth/Login"
```

</details>

<details>
<summary><strong>Quality & Tests</strong></summary>

```bash
# Check everything at once
./forge quality              # lint + PHPStan + tests
./forge quality --fix        # auto-fix style

# Individually
composer test                    # PHPUnit
composer analyse                 # PHPStan (level 5)
composer lint                    # PHP-CS-Fixer (PSR-12)
composer lint:fix                # auto-fix
```

- **PHPUnit 11** — tests in `tests/`
- **PHPStan** — static analysis level 5
- **PHP-CS-Fixer** — PSR-12 style

### Application Testing

Fennectra supports unit and feature testing in your app out of the box:

```bash
# Scaffold a test (auto-creates phpunit.xml + TestCase if missing)
./forge make:test UserService                # tests/Unit/UserServiceTest.php
./forge make:test Auth/Login --feature       # tests/Feature/Auth/LoginTest.php

# Run tests
./forge test                                 # all tests
./forge test --unit                          # unit tests only
./forge test --feature                       # feature tests only
./forge test --filter=UserServiceTest        # specific test
```

**Test structure:**

```
tests/
├── TestCase.php       ← base class (DB helpers, .env loading)
├── Unit/              ← pure logic tests (no DB)
└── Feature/           ← integration tests (with DB)
```

Unit tests extend `PHPUnit\Framework\TestCase`. Feature tests extend `Tests\TestCase` which provides `query()` and `queryOne()` helpers for database assertions.

</details>

<details>
<summary><strong>Docker & Deployment</strong></summary>

### PHP-FPM + Nginx (classic)

```bash
docker build -f docker/Dockerfile -t php-api .
docker run -p 8080:8080 --env-file .env php-api
```

### FrankenPHP Worker (max perf)

```bash
docker build -f docker/Dockerfile -t php-api:franken .
docker run -p 8080:8080 --env-file .env php-api:franken
```

FrankenPHP configuration via Caddyfile in `docker/`.

### Docker Compose (local dev)

```bash
docker compose -f docker/docker-compose.yml up -d     # starts API + PostgreSQL + Redis
docker compose logs -f   # follow logs
```

### Kubernetes

Production manifests in `docker/kubernetes/` with liveness/readiness probes.

```bash
./forge deploy              # build + push + K8s rollout
./forge deploy --dry-run    # preview without executing
```

</details>

<details>
<summary><strong>Debug Profiler</strong></summary>

Per-request profiler built into the worker. Automatically collects:

- **SQL queries**: SQL, bindings, duration in ms
- **Dispatched events**: name, listener duration
- **Middleware**: each middleware with its execution time
- **DI resolutions**: services resolved by the Container
- **Memory**: peak, delta per request
- **N+1 detection**: warning if the same query runs > 3 times

```bash
# Enable (automatic if APP_ENV=dev)
PROFILER_ENABLED=1
```

```
GET /debug/profiler        → list of the last 50 requests
GET /debug/profiler/{id}   → details of a request
```

The ring buffer persists in memory within the worker (zero I/O).

</details>

<details>
<summary><strong>Rate Limiting</strong></summary>

```php
// Per route or group
$router->group([
    'middleware' => [[RateLimitMiddleware::class, ['limit' => 30, 'window' => 60]]],
], function ($router) { /* ... */ });
```

- **Redis** in production (shared across K8s pods)
- **InMemory** in dev (zero deps)
- Automatic headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- 429 response with `Retry-After`

</details>

<details>
<summary><strong>Migrations & Seeding</strong></summary>

### Migrations

```bash
./forge make:migration create_products    # generate a file
./forge migrate                           # apply pending migrations
./forge migrate --rollback                # rollback the last batch
./forge migrate --status                  # current status
```

Format: `database/migrations/2026_03_21_143022_create_products.php`

```php
return [
    'up' => 'CREATE TABLE products (id SERIAL PRIMARY KEY, name VARCHAR(255))',
    'down' => 'DROP TABLE products',
];
```

> **Note:** The `migrations` table is automatically created with driver-appropriate SQL (`SERIAL` for PostgreSQL, `AUTO_INCREMENT` for MySQL, `AUTOINCREMENT` for SQLite).

### Seeding

```php
class UserSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 50; $i++) {
            User::create([
                'name'  => $this->fake()->name(),
                'email' => $this->fake()->email(),
            ]);
        }
    }
}
```

Built-in `FakeDataGenerator`: `name()`, `email()`, `number()`, `date()`, `uuid()`, `phone()` — zero external dependencies.

</details>

<details>
<summary><strong>Scheduler (K8s-safe)</strong></summary>

Scheduled tasks with Redis Lock — **single execution per pod** in K8s.

```php
// app/Schedule.php
return (new Schedule())
    ->call(fn() => DB::raw('DELETE FROM logs WHERE created_at < NOW() - INTERVAL \'30 days\''))
        ->daily()->name('clean-logs')
    ->command('cache:clear')
        ->everyFiveMinutes()->name('cache-refresh');
```

The scheduler runs **inside the FrankenPHP worker** (60s throttle, zero external cron).

| Method | Frequency |
|---------|-----------|
| `everyMinute()` | Every minute |
| `everyFiveMinutes()` | Every 5 minutes |
| `hourly()` | Every hour |
| `daily()` / `dailyAt('08:00')` | Daily |
| `weekly()` / `weekdays()` | Weekly |
| `cron('*/10 * * * *')` | Custom |

`SCHEDULER_ENABLED=1` + `REDIS_HOST` to activate.

</details>

<details>
<summary><strong>Job Queue</strong></summary>

```php
// Dispatch a job
Job::dispatch(SendWelcomeEmail::class, ['user_id' => 123]);

// Define a job
class SendWelcomeEmail implements JobInterface
{
    public function handle(array $payload): void { /* ... */ }
    public function retries(): int { return 3; }
    public function failed(array $payload, \Throwable $e): void { /* ... */ }
}
```

```bash
./forge queue:work                # consume (Redis BLPOP or DB polling)
./forge queue:retry --id=5        # retry a failed job
```

Drivers: `QUEUE_DRIVER=redis` (BLPOP) or `database` (FOR UPDATE SKIP LOCKED).
Failed jobs are stored in `failed_jobs`.

</details>

<details>
<summary><strong>Feature Flags</strong></summary>

```php
// Simple check
if (FeatureFlag::enabled('new-checkout')) { /* ... */ }

// Per user/role (progressive rollout)
if (FeatureFlag::for('beta-ui')->whenRole('admin')->enabled()) { /* ... */ }

// Activate/deactivate
FeatureFlag::activate('dark-mode');
FeatureFlag::deactivate('dark-mode');
```

Redis cache (60s TTL) + fallback to `feature_flags` table in DB.

```bash
./forge feature list
./forge feature enable dark-mode
```

</details>

<details>
<summary><strong>State Machine</strong></summary>

Controlled transitions on Models via attribute:

```php
#[StateMachine(column: 'status', transitions: [
    'draft->submitted', 'submitted->approved', 'submitted->rejected',
    'approved->shipped', 'shipped->delivered',
])]
class Order extends Model
{
    use HasStateMachine;
}

$order->transitionTo('submitted');       // draft → submitted OK
$order->transitionTo('shipped');         // submitted → shipped FAIL Exception
$order->canTransitionTo('approved');     // true
$order->availableTransitions();          // ['approved', 'rejected']
```

Events automatically dispatched: `Order.transitioned:submitted:approved`

</details>

<details>
<summary><strong>Multi-channel Notifications</strong></summary>

```php
// Send
$user->notify(new OrderShippedNotification($order));

// Define
class OrderShippedNotification extends Notification
{
    public function via(): array { return ['database', 'mail', 'slack']; }
    public function toMail(): MailMessage { /* ... */ }
    public function toSlack(): SlackMessage { /* ... */ }
    public function toDatabase(): array { return ['order_id' => $this->order->id]; }
}
```

Channels: **Database**, **Mail** (built-in SMTP, zero deps), **Slack** (webhook), **Webhook** (signed HTTP POST).
`HasNotifications` trait: `notify()`, `notifications()`, `unreadNotifications()`.

</details>

<details>
<summary><strong>Outgoing Webhooks</strong></summary>

Webhook system for notifying external URLs on internal events, with HMAC-SHA256 signatures and automatic retry.

### Automatic Dispatch via Events

```php
// All registered webhooks listening to 'order.shipped' will be notified
Event::dispatch('order.shipped', ['order_id' => 42, 'tracking' => 'ABC123']);
```

The `WebhookManager` listens to all events and automatically dispatches to matching webhooks via the Job Queue.

### Register a Webhook (`webhooks` table)

```sql
INSERT INTO webhooks (name, url, secret, events, is_active) VALUES (
    'Partner API',
    'https://partner.com/webhooks/orders',
    'whsec_MySharedSecret',
    '["order.shipped", "order.cancelled"]',
    true
);
```

A webhook can listen for specific events or `["*"]` to receive everything.

### HMAC-SHA256 Signature

Each request is signed — the recipient can verify authenticity:

```php
// Receiver side — verify the signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$timestamp = (int) $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'];

$isValid = WebhookManager::verify($payload, $secret, $signature, $timestamp);
// Automatically rejects requests older than 5 minutes (replay protection)
```

**Sent headers:**

| Header | Description |
|---|---|
| `X-Webhook-Event` | Event name (`order.shipped`) |
| `X-Webhook-Signature` | `sha256=hmac(timestamp.payload, secret)` |
| `X-Webhook-Timestamp` | Unix timestamp |
| `User-Agent` | `Fennec-Webhook/1.0` |

### Via the Notification System

```php
class OrderShippedNotification extends Notification
{
    public function via(): array
    {
        return ['database', 'webhook'];  // multi-channel
    }

    public function toWebhook(): WebhookMessage
    {
        return (new WebhookMessage())
            ->url('https://partner.com/hook')
            ->secret('whsec_MySecret')
            ->event('order.shipped')
            ->payload(['order_id' => $this->order->id]);
    }
}

$user->notify(new OrderShippedNotification($order));
```

### Automatic Retry

Failed deliveries are retried **5 times** with exponential backoff via the Job Queue. Each attempt is logged in the `webhook_deliveries` table:

| Column | Description |
|---|---|
| `webhook_id` | Reference to `webhooks.id` |
| `event` | Event name |
| `status` | `pending` / `delivered` / `failed` |
| `http_status` | HTTP response code |
| `response_body` | Response body (max 2000 chars) |
| `attempt` | Attempt number |

### Setup

```bash
./forge make:webhook   # generates the full module (migration + Models + DTOs + Controller + Routes)
./forge migrate        # apply the migration
```

**Generated API (admin only):**

```
GET    /webhooks                        Paginated list
GET    /webhooks/{id}                   Details
POST   /webhooks                        Create
PUT    /webhooks/{id}                   Update
DELETE /webhooks/{id}                   Delete
PATCH  /webhooks/{id}/toggle            Enable/disable
GET    /webhooks/{id}/deliveries        Deliveries
GET    /webhooks/stats                  Statistics
GET    /webhooks/failures               Recent failures
POST   /webhooks/deliveries/{id}/retry  Retry
```

</details>

<details>
<summary><strong>Image Transforms</strong></summary>

Image transformation via [Intervention/Image v3](https://image.intervention.io/v3) — resize, crop, blur, watermark, format conversion and more. Integrates with the existing Storage system.

### Quick Transforms

```php
// Resize (preserves aspect ratio)
ImageTransformer::resize('photos/avatar.jpg', 800);
ImageTransformer::resize('photos/avatar.jpg', 800, 600);

// Square thumbnail 150x150
ImageTransformer::thumbnail('photos/avatar.jpg', 150);

// Cover (fills exact dimensions)
ImageTransformer::fit('photos/banner.jpg', 1200, 630);

// Crop a region
ImageTransformer::crop('photos/photo.jpg', 400, 400, 50, 50);

// Convert to WebP
ImageTransformer::convert('photos/large.png', 'webp', 85);
```

Each method returns the **path of the transformed file** in Storage.

### Chainable Pipeline

For complex transformations, the pipeline lets you chain operations:

```php
$outputPath = ImageTransformer::make('photos/original.jpg')
    ->orient()                              // automatic EXIF correction
    ->resize(1200)                          // max 1200px wide
    ->crop(800, 600, 100, 50)              // crop a region
    ->blur(3)                               // gaussian blur
    ->sharpen(15)                           // sharpness
    ->brightness(10)                        // brightness
    ->contrast(5)                           // contrast
    ->greyscale()                           // black and white
    ->watermark('My App', 'bottom-right', 20, 'ffffff', 50)
    ->format('webp', 85)                   // conversion + quality
    ->apply();                              // save to Storage

// $outputPath = 'photos/transforms/original_a1b2c3d4.webp'
```

### Direct Buffer (HTTP response)

```php
// Return the transformed image without saving it
$buffer = ImageTransformer::make('photos/avatar.jpg')
    ->fit(200, 200)
    ->format('webp')
    ->toBuffer();

header('Content-Type: image/webp');
echo $buffer;
```

### Available Operations

| Method | Description |
|---|---|
| `resize(w, h)` | Resize (preserves aspect ratio) |
| `resizeExact(w, h)` | Resize (stretch, no ratio) |
| `crop(w, h, x, y)` | Crop a region |
| `fit(w, h)` | Cover — fills exact dimensions |
| `blur(amount)` | Gaussian blur (1-100) |
| `sharpen(amount)` | Increase sharpness (1-100) |
| `brightness(level)` | Brightness (-100 to +100) |
| `contrast(level)` | Contrast (-100 to +100) |
| `rotate(angle)` | Rotation in degrees |
| `flip()` | Horizontal mirror |
| `flop()` | Vertical mirror |
| `greyscale()` | Greyscale |
| `orient()` | Automatic EXIF correction |
| `watermark(text, pos, size, color, opacity)` | Text watermark |
| `format(fmt, quality)` | Output format (jpg, png, webp, gif) |
| `quality(q)` | Output quality (1-100) |

### Pipeline Cache

Each pipeline generates a **unique cache key** based on the path + operations:

```php
$pipeline = ImageTransformer::make('photo.jpg')->resize(800)->format('webp');
$cacheKey = $pipeline->cacheKey();  // 'img:a1b2c3d4e5f6...'

// Use with the framework's Cache
$result = Cache::remember($cacheKey, 86400, fn() => $pipeline->apply());
```

### Attribute for Controllers

```php
#[ImageTransform(maxWidth: 2000, maxHeight: 2000, allowedFormats: ['jpg', 'png', 'webp'])]
public function transform(string $path): void
{
    $buffer = ImageTransformer::make($path)
        ->resize((int) ($_GET['w'] ?? 800))
        ->format($_GET['fmt'] ?? 'webp', (int) ($_GET['q'] ?? 85))
        ->toBuffer();

    header('Content-Type: ' . ImageTransformer::mimeType($_GET['fmt'] ?? 'webp'));
    header('Cache-Control: public, max-age=86400');
    echo $buffer;
}
```

### Supported Formats

| Format | Read | Write | MIME |
|---|---|---|---|
| JPEG | Yes | Yes | `image/jpeg` |
| PNG | Yes | Yes | `image/png` |
| WebP | Yes | Yes | `image/webp` |
| GIF | Yes | Yes | `image/gif` |

> **Driver**: GD by default. For optimal performance and animated GIF support, install the Imagick extension and instantiate with `new ImageTransformer(ImageManager::imagick())`.

</details>

<details>
<summary><strong>SSE Broadcasting</strong></summary>

Server-Sent Events for real-time:

```php
// Server side
Broadcaster::broadcast('orders', 'shipped', ['order_id' => 42]);

// Client side (JavaScript)
const es = new EventSource('/events/stream?channels=orders');
es.onmessage = (e) => console.log(JSON.parse(e.data));
```

- Redis Pub/Sub for cross-pod communication
- Heartbeat every 15s
- `#[Broadcast('channel')]` attribute for auto-broadcast

</details>

<details>
<summary><strong>OAuth / OpenID Connect / SAML 2.0</strong></summary>

The framework supports 3 SSO protocols with a unified `OAuthUser` output:

| Protocol | Use case | Providers |
|---|---|---|
| **OAuth2** | Social login | Google, GitHub |
| **OIDC** | Enterprise/government SSO | Any OIDC-compliant IdP (France Travail, Keycloak, Azure AD, Auth0, Okta...) |
| **SAML 2.0** | Enterprise SSO | Active Directory, Okta, Azure AD, Keycloak... |

### OAuth2 (Google, GitHub)

```php
use Fennec\Core\OAuth\OAuthManager;

$oauth = new OAuthManager();
$google = $oauth->driver('google');
$url = $google->getAuthorizationUrl($state);
// after callback...
$token = $google->getAccessToken($code);
$user = $google->getUserInfo($token->accessToken);
```

```env
OAUTH_GOOGLE_CLIENT_ID=...
OAUTH_GOOGLE_CLIENT_SECRET=...
OAUTH_GOOGLE_REDIRECT=http://localhost:8080/auth/google/callback
```

### OpenID Connect (any OIDC provider)

```php
$oidc = $oauth->driver('oidc');
$url = $oidc->getAuthorizationUrl($state);  // auto-discovery + nonce + optional PKCE
// after callback...
$token = $oidc->getAccessToken($code, $codeVerifier);
$claims = $oidc->validateIdToken($token->idToken, $nonce);  // JWKS signature validation
$user = $claims->toOAuthUser('oidc');

// Register custom OIDC providers
$oauth->extend('france_travail', fn () => new GenericOidcProvider(
    issuer: 'https://authentification-candidat.francetravail.fr/connexion/oauth2',
    clientId: Env::get('FT_CLIENT_ID'),
    clientSecret: Env::get('FT_CLIENT_SECRET'),
    redirectUri: Env::get('FT_REDIRECT'),
    pkce: true,
));
```

```env
OIDC_ISSUER=https://idp.example.com
OIDC_CLIENT_ID=...
OIDC_CLIENT_SECRET=...
OIDC_REDIRECT=http://localhost:8080/auth/oidc/callback
OIDC_PKCE=true
```

### SAML 2.0 (enterprise SSO)

```php
use Fennec\Core\Saml\SamlConfig;
use Fennec\Core\Saml\SamlServiceProvider;

$sp = new SamlServiceProvider(SamlConfig::fromEnv());

// Redirect to IdP
$result = $sp->buildAuthnRequestUrl('/dashboard');
header('Location: ' . $result['url']);

// Handle POST callback
$samlUser = $sp->processResponse($_POST['SAMLResponse'], $result['id']);
$user = $samlUser->toOAuthUser();  // unified OAuthUser

// Serve SP metadata for IdP auto-configuration
echo $sp->generateMetadata();
```

```env
SAML_SP_ENTITY_ID=https://myapp.com
SAML_SP_ACS_URL=https://myapp.com/saml/acs
SAML_IDP_ENTITY_ID=https://idp.example.com
SAML_IDP_SSO_URL=https://idp.example.com/sso
SAML_IDP_CERTIFICATE="-----BEGIN CERTIFICATE-----..."
```

Zero external dependencies — native PHP `ext-dom` + `ext-openssl`.

</details>

<details>
<summary><strong>SOC 2 Compliance</strong></summary>

The framework includes the technical controls required for SOC 2 Type II.

### Audit Trail

Automatic tracking of create/update/delete on Models:

```php
#[Table('users'), Auditable(except: ['password'])]
class User extends Model
{
    use HasAuditTrail;
}

// Generate the full module (migration + Model + DTOs + Controller + Routes)
./forge make:audit
./forge migrate
```

Each mutation records: `auditable_type`, `auditable_id`, `action`, `old_values`, `new_values`, `user_id`, `ip_address`, `request_id`, `created_at`.

### Security Event Logger

Dedicated Monolog channel `security` → `var/logs/security.log` + stderr (K8s ready):

```php
SecurityLogger::alert('auth.failed', ['email' => $email]);
SecurityLogger::track('token.revoked', ['user_id' => 42]);
SecurityLogger::critical('brute_force.detected', ['attempts' => 100]);
```

Events automatically logged by middleware:
- `auth.missing_token`, `auth.invalid_token`, `auth.revoked_token`, `auth.insufficient_role`
- `rate_limit.exceeded`

Each entry is enriched with: `request_id`, `ip`, `uri`, `method`, `user`, `timestamp`.

### Encryption at Rest (AES-256-GCM)

Transparent encryption of sensitive fields in the database:

```php
#[Table('users')]
class User extends Model
{
    use HasEncryptedFields;

    #[Encrypted]
    public string $phone;

    #[Encrypted]
    public string $address;
}

// Values are encrypted in DB (enc: prefix) and decrypted on read
$user->phone = '+33612345678';  // stored: enc:base64(iv+tag+cipher)
$user->save();
echo $user->phone;               // +33612345678
```

```env
# Generate: php -r "echo base64_encode(random_bytes(32));"
ENCRYPTION_KEY=your_base64_32_byte_key
```

### Token Hardening

Configurable TTL (SOC 2 compliant defaults):

```env
JWT_ACCESS_TTL=900       # 15 minutes (default)
JWT_REFRESH_TTL=86400    # 24 hours (default)
```

### CORS Whitelist

Controlled origins in production:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

- In dev (`APP_ENV=dev`): everything allowed
- In prod: only listed origins receive CORS headers
- Without config in prod: no origins allowed

### Log Masking

Automatic masking of sensitive data in all logs:

```
password, token, secret, authorization, credit_card, ssn, api_key → ***
```

Configurable via `LOG_MASK_FIELDS` to add custom keys.

### SOC 2 Summary

| Criterion | Control | Component |
|---------|----------|-----------|
| Traceability | Audit trail on Models + admin API | `#[Auditable]` + `HasAuditTrail` + `make:audit` |
| Incident detection | Security event logging | `SecurityLogger` |
| Confidentiality | Encryption at rest | `#[Encrypted]` + AES-256-GCM |
| Limited sessions | Token TTL 15min/24h | `JWT_ACCESS_TTL` / `JWT_REFRESH_TTL` |
| Access control | CORS whitelist | `CORS_ALLOWED_ORIGINS` |
| Log protection | Sensitive data masking | `LogMaskingProcessor` |

</details>

<details>
<summary><strong>ISO 27001 Compliance</strong></summary>

ISO 27001 Annex A technical controls built into the framework.

### Password Policy (A.8.5)

Password strength validation:

```php
$errors = PasswordPolicy::validate($password);
// Checks: length (12+), uppercase, lowercase, digit, special, common words

PasswordPolicy::assertValid($password); // RuntimeException if invalid

$score = PasswordPolicy::strength($password); // 0-5
```

```env
PASSWORD_MIN_LENGTH=12    # configurable
```

### Account Lockout (A.8.5)

Automatic lockout after N failed attempts:

```php
if (AccountLockout::isLocked($email)) {
    // account locked, return 429
}

AccountLockout::recordFailure($email);  // +1 attempt
AccountLockout::reset($email);          // after successful login
```

```env
LOCKOUT_MAX_ATTEMPTS=5    # attempts before lockout
LOCKOUT_DURATION=900      # 15 min lockout
```

Automatically integrated into `TokenController` — each login failure/success is logged in `SecurityLogger`.

### IP Allowlist (A.8.5)

IP-based access restriction for sensitive routes:

```php
$router->group([
    'middleware' => [[IpAllowlistMiddleware::class]],
], function ($router) {
    // admin routes only accessible from allowed IPs
});
```

```env
IP_ALLOWLIST=10.0.0.0/8,192.168.1.0/24,127.0.0.1
```

Supports exact IPs and CIDRs. Without configuration, the middleware allows everything (opt-in).

### Log Integrity HMAC (A.8.15)

Each `SecurityLogger` entry includes a chained HMAC SHA-256:

```
{"event": "auth.failed", ..., "_hmac": "a1b2c3..."}
```

Each entry's HMAC depends on the previous HMAC — any deletion or modification of a line breaks the chain. Key derived from `SECRET_KEY`.

### Data Retention (A.5.33)

Automatic purge of old audit logs:

```bash
./forge audit:purge                  # purge > 365 days (default)
./forge audit:purge --days=90        # purge > 90 days
./forge audit:purge --dry-run        # preview without deleting
```

```env
AUDIT_RETENTION_DAYS=365
```

### Login Auditing (A.8.15)

All authentication events are logged in `security.log`:

| Event | When |
|---|---|
| `auth.login_success` | Successful login |
| `auth.login_failed` | Incorrect password or unknown user |
| `auth.account_locked` | Account locked (too many attempts) |
| `auth.missing_token` | Request without Bearer token |
| `auth.invalid_token` | Invalid or expired JWT |
| `auth.revoked_token` | Token revoked in database |
| `auth.insufficient_role` | Insufficient role |
| `rate_limit.exceeded` | Rate limit exceeded |
| `access.ip_blocked` | Unauthorized IP |

### ISO 27001 Summary

| Annex A Control | Implementation |
|---|---|
| A.8.5 Secure authentication | `PasswordPolicy` + `AccountLockout` |
| A.8.5 Access control | `IpAllowlistMiddleware` |
| A.8.12 Data leak prevention | `LogMaskingProcessor` on all loggers |
| A.8.15 Integrated logging | HMAC chain + complete auth event auditing |
| A.5.33 Data retention | `audit:purge` command |

</details>

<details>
<summary><strong>NF525 — Certified Invoicing</strong></summary>

NF525 compliance module for invoicing software in France. Covers the 4 pillars: immutability, security, preservation, and archiving.

### Setup

```bash
./forge make:nf525    # generates the full module (migration + 4 Models + DTOs + Controller + Routes)
./forge migrate       # create the tables
```

**Generated API (admin only):**

```
GET    /nf525/invoices              List invoices
GET    /nf525/invoices/{id}         Details with lines
POST   /nf525/invoices              Create an invoice
POST   /nf525/invoices/{id}/credit  Create a credit note
GET    /nf525/closings              List closings
POST   /nf525/closings              Trigger a closing
GET    /nf525/verify                Verify the hash chain
GET    /nf525/fec/export            Export the FEC
GET    /nf525/journal               Event journal
GET    /nf525/stats                 NF525 statistics
```

### Immutable Invoice Model

```php
#[Table('invoices'), Nf525(prefix: 'FA')]
class Invoice extends Model
{
    use HasNf525;
}

// Create an invoice — automatic numbering + hash chain
$invoice = Invoice::create([
    'client_name' => 'SARL Dupont',
    'total_ht' => 1000.00,
    'tva' => 200.00,
    'total_ttc' => 1200.00,
]);
// number: FA-2026-000001
// hash: sha256(previous_hash + data)

// Modify? FORBIDDEN
$invoice->total_ht = 500;
$invoice->save();  // RuntimeException: NF525 — modification forbidden

// Delete? FORBIDDEN
$invoice->delete();  // RuntimeException: NF525 — deletion forbidden

// Correct? Via credit note
$credit = $invoice->createCredit('Amount error');
// FA-2026-000002 (credit note, negative amounts, references the original invoice)
```

### Signed Periodic Closings

```bash
./forge nf525:close --daily=2026-03-22     # daily closing
./forge nf525:close --monthly=2026-03       # monthly closing
./forge nf525:close --annual=2026           # annual closing
```

Each closing generates: HT/VAT/TTC totals, document count, cumulative grand total, HMAC-SHA256 hash chained with the previous closing.

### FEC Export (Accounting Entries File)

```bash
./forge nf525:export --year=2026                    # export in FEC format
./forge nf525:export --year=2026 --output=FEC.txt   # to a specific file
```

Standardized TSV format: JournalCode, EcritureDate, CompteNum, CompteLib, Debit, Credit, etc.

### Integrity Verification

```bash
./forge nf525:verify                    # verify the invoices table
./forge nf525:verify --table=invoices   # specific table
```

Traverses the entire hash chain and detects anomalies (modification, deletion, insertion).

### The 4 NF525 Pillars

| Pillar | Implementation |
|---|---|
| **Immutability** | `HasNf525` trait — blocks update/delete, corrections via credit notes |
| **Security** | Sequential SHA-256 hash chain on each invoice |
| **Preservation** | `ClosingService` — signed closings (daily/monthly/annual) |
| **Archiving** | `FecExporter` — standardized FEC export for the tax authority |

### Tables Generated by make:nf525

| Table | Purpose |
|---|---|
| `invoices` | Invoices with hash chain (number, hash, previous_hash) |
| `invoice_lines` | Invoice lines (description, quantity, price, VAT) |
| `nf525_closings` | Periodic closings (totals, HMAC hash, cumulative) |
| `nf525_journal` | NF525 event journal |

</details>

<details>
<summary><strong>GDPR — Consent & Compliance</strong></summary>

Complete GDPR consent management module with legal document versioning, traceability, and data subject rights.

### Setup

```bash
./forge make:rgpd    # generates the full module (migration + Models + DTOs + Controller + Routes)
./forge migrate      # create the tables
```

**11 files generated:**

```
database/migrations/..._create_rgpd_tables.php
app/Models/ConsentObject.php          # versioned legal documents
app/Models/UserConsent.php            # consents + DPO functions
app/Dto/ConsentObject*.php            # 4 DTOs
app/Dto/UserConsentRequest.php
app/Dto/RgpdStatsResponse.php
app/Controllers/ConsentController.php # 14 endpoints
app/Routes/consent.php                # 4 route groups by role
```

### Versioned Legal Documents

```php
// Create a new version (automatic chaining)
ConsentObject::createNewVersion('tos', 'Terms of Service v3', '<h1>...</h1>', isRequired: true);
// object_version: 3, object_previous_version: id_v2

// Latest version by key
ConsentObject::latestByKey('tos');      // latest active version
ConsentObject::allLatest();             // all keys, latest version
```

### User Consent

```php
// Record a consent
UserConsent::recordConsent($userId, $docId, status: true, objectVersion: 3, way: 'web');

// Check compliance
UserConsent::hasAcceptedAll($userId);   // true if all accepted (latest version)

// History (GDPR right of access)
UserConsent::userHistory($userId);

// Export (GDPR right to portability)
UserConsent::exportForUser($userId);

// Withdrawal (GDPR right to erasure)
UserConsent::withdrawAll($userId);
```

### DPO Dashboard

```php
// Compliance rate
UserConsent::complianceRate();
// { total_active_users: 15000, compliant_users: 14950, compliance_rate: 99.67 }

// Stats per document
UserConsent::statsByDocument();
// [{ object_name: 'ToS', accepted: 15304, refused: 26 }, ...]

// Non-compliant users
UserConsent::nonCompliantUsers(limit: 50);
```

### API (14 endpoints)

```
Public:
  GET    /consent/documents/{key}/latest       Latest version of a document

Authenticated user (all roles):
  POST   /consent/me                           Give consent
  GET    /consent/me                           My consent status
  DELETE /consent/me                           Withdraw my consents

Admin:
  GET    /consent/documents                    List documents
  GET    /consent/documents/{id}               Document details
  POST   /consent/documents                    Create a new version

DPO / Admin:
  GET    /consent/dpo/dashboard                Full dashboard
  GET    /consent/dpo/stats                    Stats per document
  GET    /consent/dpo/compliance               Compliance rate
  GET    /consent/dpo/non-compliant            Non-compliant users
  GET    /consent/dpo/users/{id}/history       User history
  GET    /consent/dpo/users/{id}/export        Portability export
  DELETE /consent/dpo/users/{id}/consents      Right to erasure
```

### Tables

| Table | Purpose |
|---|---|
| `consent_objects` | Versioned legal documents (ToS, legal notices, privacy policy) |
| `user_consents` | User consents with traceability (status, method, version, dates) |

### GDPR Rights Covered

| Right | Endpoint |
|---|---|
| Right of access (art. 15) | `GET /consent/dpo/users/{id}/history` |
| Right to portability (art. 20) | `GET /consent/dpo/users/{id}/export` |
| Right to object (art. 21) | `DELETE /consent/me` |
| Right to erasure (art. 17) | `DELETE /consent/dpo/users/{id}/consents` |
| Proof of consent (art. 7) | `user_consents` table (consent_way, timestamp, version) |

</details>

<details>
<summary><strong>Redis Cache</strong></summary>

```php
// Simple API
$user = Cache::remember("user:{$id}", 3600, fn() => User::find($id));
Cache::forget("user:{$id}");

// Tags (group invalidation)
Cache::tags(['users'])->set("user:{$id}", $user, 3600);
Cache::tags(['users'])->flush();  // invalidate the entire group
```

`RedisConnection` shared with RateLimiter, Scheduler, Queue and EventDispatcher.

</details>

<details>
<summary><strong>PDF Generation (dompdf)</strong></summary>

PDF generation from HTML/CSS via [dompdf](https://github.com/dompdf/dompdf).

```php
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml('<h1>My Invoice</h1><p>HTML content...</p>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
echo $dompdf->output();
```

### Demo

```
GET /pdf/demo   → sample invoice generated as PDF
```

### Useful Options

| Option | Description |
|--------|-------------|
| `defaultFont` | Default font (Helvetica, Courier, Times) |
| `isRemoteEnabled` | Load external images/CSS (`false` by default) |
| `setPaper('A4', 'landscape')` | Format and orientation |
| `$dompdf->output()` | Returns PDF content as string |
| `stream('file.pdf')` | Forces browser download |

### Tips

- Use `<table>` for layouts (better dompdf support than flexbox/grid)
- `position: fixed; bottom: 30px;` to stick a footer at the bottom of the page
- Prefer standard fonts (Helvetica, Courier, Times) or embed custom fonts

</details>

<details>
<summary><strong>Tutorials</strong></summary>

12 tutorials with corrected exercises in `Tutos/` — open `Tutos/index.html` in a browser.

**Worker Mode:**

| # | Title | Level |
|---|-------|--------|
| 01 | Lifecycle & Golden Rules | Beginner |
| 02 | Patterns & Architecture | Intermediate |
| 03 | Memory & Monitoring | Intermediate |
| 04 | Worker Exercises | All levels |

**Framework:**

| # | Title | Level |
|---|-------|--------|
| 05 | Routing & Middleware | Beginner |
| 06 | ORM & Query Builder | Intermediate |
| 07 | Auth, DI & Events | Intermediate |
| 08 | Framework Exercises | All levels |

**Advanced Features:**

| # | Title | Level |
|---|-------|--------|
| 09 | Profiler, Rate Limiting & Security | Intermediate |
| 10 | Migrations, Seeding & Cache | Beginner-Intermediate |
| 11 | Scheduler, Queue & Feature Flags | Intermediate |
| 12 | Notifications, SSE & OAuth | Intermediate-Advanced |

</details>

<details>
<summary><strong>Configuration (.env)</strong></summary>

```env
# Database driver (pgsql | mysql | sqlite)
DB_DRIVER=pgsql

# PostgreSQL (if DB_DRIVER=pgsql)
POSTGRES_HOST=localhost
POSTGRES_PORT=5432
POSTGRES_DB=fennectra
POSTGRES_USER=fennectra
POSTGRES_PASSWORD=secret

# MySQL (if DB_DRIVER=mysql)
# MYSQL_HOST=localhost
# MYSQL_PORT=3306
# MYSQL_DB=myapp
# MYSQL_USER=root
# MYSQL_PASSWORD=secret

# SQLite (if DB_DRIVER=sqlite)
# SQLITE_DB=var/database.sqlite

# JWT
SECRET_KEY=your_jwt_key_32_chars_minimum

# Event Broker (sync | redis | database)
EVENT_BROKER=sync

# Redis (events, cache, rate limit, scheduler, queue)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_PREFIX=app:events:

# Queue
QUEUE_DRIVER=redis

# Scheduler (inside the FrankenPHP worker)
SCHEDULER_ENABLED=1

# Profiler
PROFILER_ENABLED=1

# OAuth (Google, GitHub)
OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_GOOGLE_REDIRECT=http://localhost:8080/auth/google/callback

# OpenID Connect (any OIDC provider)
# OIDC_ISSUER=https://idp.example.com
# OIDC_CLIENT_ID=
# OIDC_CLIENT_SECRET=
# OIDC_REDIRECT=http://localhost:8080/auth/oidc/callback
# OIDC_SCOPES=email,profile
# OIDC_PKCE=false

# SAML 2.0 (enterprise SSO)
# SAML_SP_ENTITY_ID=https://myapp.com
# SAML_SP_ACS_URL=https://myapp.com/saml/acs
# SAML_IDP_ENTITY_ID=https://idp.example.com
# SAML_IDP_SSO_URL=https://idp.example.com/sso
# SAML_IDP_CERTIFICATE=/path/to/idp-cert.pem
# SAML_WANT_SIGNED=true

# Notifications
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USER=
MAIL_PASSWORD=
MAIL_FROM=noreply@example.com
SLACK_WEBHOOK_URL=

# Deploy
DEPLOY_REGISTRY=europe-west9-docker.pkg.dev/project/repo
DEPLOY_IMAGE=php-app
DEPLOY_NAMESPACE=default

# SOC 2 — Security & Compliance
# JWT_ACCESS_TTL=900
# JWT_REFRESH_TTL=86400
# ENCRYPTION_KEY=
# CORS_ALLOWED_ORIGINS=https://app.example.com
# LOG_MASK_FIELDS=custom_field

# ISO 27001 — Access Controls
# PASSWORD_MIN_LENGTH=12
# LOCKOUT_MAX_ATTEMPTS=5
# LOCKOUT_DURATION=900
# IP_ALLOWLIST=10.0.0.0/8,127.0.0.1
# AUDIT_RETENTION_DAYS=365

# Environment
APP_ENV=dev
```

</details>

---

<details>
<summary><strong>Tutorial: Full CRUD in 1 Command</strong></summary>

```bash
./forge make:all Product --roles=admin,manager
```

**5 files generated:**

```
app/Models/Product.php                ← ORM Model
app/Dto/ProductRequest.php            ← input DTO (validation)
app/Dto/ProductResponse.php           ← output DTO
app/Controllers/ProductController.php ← CRUD controller
app/Routes/product.php                ← REST Routes
```

**Created routes:**

```
GET    /product          →  index   (paginated list)
GET    /product/{id}     →  show    (details)
POST   /product          →  store   (create)
PUT    /product/{id}     →  update  (modify)
DELETE /product/{id}     →  delete  (delete)
```

**Variants:**

```bash
./forge make:all Invoice --connection=job --roles=admin   # secondary database
./forge make:all Article --no-auth                        # without auth
```

### Full Business Modules

The following `make:*` commands generate a complete module (migration + Models + DTOs + Controller + Routes with roles):

```bash
./forge make:rgpd       # GDPR consent (14 endpoints, DPO dashboard)
./forge make:audit      # SOC 2 audit trail (6 endpoints, admin)
./forge make:webhook    # HMAC-SHA256 webhooks (10 endpoints, admin)
./forge make:nf525      # NF525 invoicing (10 endpoints, admin)
```

Each command is **idempotent** — re-running does not duplicate anything.

</details>

---

**Dependencies:** `monolog/monolog` · `firebase/php-jwt` · `dompdf/dompdf` · `intervention/image` · `aws/aws-sdk-php` · `google/cloud-storage`
**PHP:** >= 8.3 | **Runtime:** FrankenPHP Worker or PHP-FPM | **License:** MIT
