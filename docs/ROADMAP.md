```
 _____ _____ _   _ _   _ _____ ____ _____ ____      _
|  ___| ____| \ | | \ | | ____/ ___|_   _|  _ \   / \
| |_  |  _| |  \| |  \| |  _|| |     | | | |_) | / _ \
|  _| | |___| |\  | |\  | |__| |___  | | |  _ < / ___ \
|_|   |_____|_| \_|_| \_|_____\____| |_| |_| \_/_/   \_\

                    R O A D M A P
```

---

## Overview

```
+---------------------------------------------------------------------+
|                                                                     |
|   PHASE 1 — Foundations            PHASE 2 — Differentiation        |
|   Robustness & Security           Premium Features                  |
|   ........................         ........................          |
|   . Debug Profiler                 . Scheduler + Redis Lock         |
|   . Eager Loading with()          . Job Queue CLI worker            |
|   . Rate Limiting                  . Feature Flags                  |
|   . Security Headers              . State Machine                  |
|   . Request Logging               . Multi-channel Notifications    |
|   . Tests (60%+ coverage)         . Full-text Search               |
|                                                                     |
|   PHASE 3 — DX & Ops              PHASE 4 — Ecosystem              |
|   Developer Experience             Extras                           |
|   ........................         ........................          |
|   . docker-compose.yml            . Broadcasting SSE               |
|   . Migrations                     . OAuth / Socialite              |
|   . Seeding                        . Cashier / Billing             |
|   . Readiness/Liveness probes     . Auto-generated Admin API       |
|   . Redis distributed cache       . Deploy CLI (K8s)               |
|   . API Versioning                                                  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

# PHASE 1 — Foundations

> Goal: make the framework robust, secure, and debuggable.

---

<details>
<summary><h2>1.1 — Debug Profiler</h2></summary>

### Why
Symfony has the Profiler Toolbar, Laravel has Telescope/Pulse. No micro-framework has a built-in profiler. This is the **#1 differentiator**.

### What it does
A per-request metrics collector, viewable via `GET /debug/profiler`.

### Metrics to collect

| Metric | Source | Hook |
|----------|--------|------|
| Total request time | `App::handle()` | `microtime(true)` start/end |
| All SQL queries | `Database::execute()` | PDO wrapper with timer |
| Query bindings | `QueryBuilder` | Param logging |
| Auto N+1 detection | `Model::belongsTo/hasMany` | Counter per relation/request |
| Dispatched events | `EventDispatcher::dispatch()` | Log event + called listeners |
| Listener duration | `EventDispatcher` | Timer per listener |
| Traversed middlewares | `MiddlewarePipeline` | Timer per middleware |
| Resolved services (DI) | `Container::get()` | Resolution logging |
| Peak memory + delta | `memory_get_peak_usage()` | Request start/end |
| Matched route + params | `Router::dispatch()` | Match logging |
| Req/res headers | `Request` / `Response` | Capture |
| Status code + body size | `Response` | Capture |

### Architecture

```
+----------------------------------------------------------+
|                     Profiler                             |
|                                                          |
|   +------------+  +------------+  +------------+       |
|   |  DB Panel  |  |Event Panel |  | Route Panel|       |
|   |  queries[] |  | events[]   |  | match info |       |
|   |  total_ms  |  | listeners[]|  | middleware[]|       |
|   +-----+------+  +-----+------+  +-----+------+       |
|         |               |               |               |
|         +---------------+---------------+               |
|                         v                               |
|                 +--------------+                        |
|                 |  Collector   | <- stores the last N   |
|                 |  (in-memory) |   requests in worker   |
|                 +------+-------+                        |
|                        |                                |
|                        v                                |
|              GET /debug/profiler                        |
|              GET /debug/profiler/{id}                   |
+----------------------------------------------------------+
```

### Files to create

```
src/Core/Profiler/
├── Profiler.php              <- main collector, start/stop per request
├── ProfilerMiddleware.php    <- global middleware that starts/stops profiling
├── Panel/
│   ├── PanelInterface.php    <- common interface
│   ├── DatabasePanel.php     <- hook into Database::execute()
│   ├── EventPanel.php        <- hook into EventDispatcher::dispatch()
│   ├── MiddlewarePanel.php   <- hook into MiddlewarePipeline
│   ├── ContainerPanel.php    <- hook into Container::get()
│   ├── MemoryPanel.php       <- memory_get_peak_usage()
│   └── RoutePanel.php        <- hook into Router::dispatch()
├── Storage/
│   ├── StorageInterface.php  <- interface for profile storage
│   ├── InMemoryStorage.php   <- ring buffer of the last 50 profiles (worker)
│   └── FileStorage.php       <- file fallback (classic dev)
└── ProfilerController.php    <- endpoints /debug/profiler
```

### Behavior

- **Dev**: active by default, stores the last 50 requests in memory (worker) or file
- **Prod**: disabled by default, activable via `PROFILER_ENABLED=true`
- **Worker**: the ring buffer lives in the worker -> zero I/O, zero overhead when disabled
- **JSON endpoint**: `GET /debug/profiler` returns the list, `GET /debug/profiler/{id}` the detail

### Example output

```json
{
  "id": "req_abc123",
  "timestamp": "2026-03-21T14:32:00Z",
  "method": "GET",
  "uri": "/admin/users",
  "status": 200,
  "duration_ms": 12.4,
  "memory_peak_mb": 2.1,
  "db": {
    "query_count": 3,
    "total_ms": 4.2,
    "n_plus_one": false,
    "queries": [
      { "sql": "SELECT * FROM users WHERE active = $1", "bindings": [true], "ms": 1.8 },
      { "sql": "SELECT * FROM roles WHERE id = $1", "bindings": [2], "ms": 1.2 },
      { "sql": "SELECT count(*) FROM users", "bindings": [], "ms": 1.2 }
    ]
  },
  "events": [
    { "name": "user.listed", "listeners": 1, "ms": 0.3 }
  ],
  "middleware": [
    { "class": "CorsMiddleware", "ms": 0.1 },
    { "class": "AuthMiddleware", "ms": 0.8 }
  ],
  "route": {
    "pattern": "/admin/users",
    "controller": "AdminController::listUsers",
    "params": {}
  }
}
```

</details>

---

<details>
<summary><h2>1.2 — Eager Loading <code>with()</code></h2></summary>

### Why
N+1 is the #1 trap of any ORM. Eloquent lazy-loads by default. Doctrine leaks memory with the Identity Map. We can do better.

### What it does

```php
// BEFORE — N+1: 1 users query + N roles queries
$users = User::where('active', true)->get();
foreach ($users as $user) {
    echo $user->role()->name;  // <- 1 query per user
}

// AFTER — 2 queries total
$users = User::with('role')->where('active', true)->get();
foreach ($users as $user) {
    echo $user->role()->name;  // <- already loaded, 0 queries
}
```

### Strategy

```
Query 1: SELECT * FROM users WHERE active = true
          -> collect role_ids: [1, 2, 3]

Query 2: SELECT * FROM roles WHERE id IN (1, 2, 3)
          -> map by id

Hydration: user->_relations['role'] = roles[user->role_id]
```

### Files to modify

```
src/Core/Model.php              <- add with(), _relations[], __get() override
src/Core/ModelQueryBuilder.php   <- add with(), eagerLoad() after get()
```

### Target API

```php
// Simple eager load
User::with('role')->get();

// Multiple relations
User::with('role', 'profile')->get();

// Eager load on find
User::with('role')->find(123);

// Eager load on paginate
User::with('role')->paginate(20);

// N+1 detection (dev mode)
// If a relation is accessed without with() -> warning in the Profiler
```

### Profiler Integration
The DatabasePanel automatically detects N+1 patterns:
- If the same query `SELECT * FROM roles WHERE id = ?` is executed > 3 times per request -> flag `n_plus_one: true`

</details>

---

<details>
<summary><h2>1.3 — Rate Limiting Middleware</h2></summary>

### Why
No protection against brute-force or API abuse. This is the basics of API security.

### What it does

```php
// By attribute on the controller
#[RateLimit(60, 'minute')]
public function index(Request $request): array { }

// Granular attribute
#[RateLimit(5, 'minute', by: 'ip')]       // login
#[RateLimit(1000, 'hour', by: 'user')]    // API usage

// By route group
$router->group([
    'middleware' => [[RateLimitMiddleware::class, [100, 'minute']]],
], function ($router) { /* ... */ });
```

### Strategies

| Strategy | Description | Usage |
|-----------|------------|-------|
| **Fixed Window** | X requests per time window | Simple, good for most cases |
| **Sliding Window** | Sliding window | More precise, avoids edge bursts |
| **Token Bucket** | Tokens consumed and regenerated | Ideal for APIs with allowed bursts |

### Storage

| Backend | Context | Advantage |
|---------|---------|----------|
| **In-Memory** (worker) | Dev / Single pod | Zero dep, ultra-fast |
| **Redis** | K8s / Multi-pod | Shared between replicas |

### Response headers

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1711029600
Retry-After: 30              <- if 429
```

### Files to create

```
src/Core/RateLimit/
├── RateLimiter.php              <- counting logic
├── RateLimitMiddleware.php      <- HTTP middleware
├── Storage/
│   ├── StorageInterface.php
│   ├── InMemoryStorage.php      <- for worker (dev)
│   └── RedisStorage.php         <- for K8s (prod)
src/Attributes/
└── RateLimit.php                <- #[RateLimit(60, 'minute')]
```

### 429 Response

```json
{
  "status": "error",
  "message": "Too many requests",
  "retry_after": 30
}
```

</details>

---

<details>
<summary><h2>1.4 — Security Headers Middleware</h2></summary>

### Why
Zero security headers currently. Every response should include standard protections.

### Headers to add

```
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-XSS-Protection: 0
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

### File to create

```
src/Middleware/SecurityHeadersMiddleware.php
```

### Integration
Add as global middleware in `App.php`:

```php
$router->addGlobalMiddleware(SecurityHeadersMiddleware::class);
```

</details>

---

<details>
<summary><h2>1.5 — Request Logging Middleware</h2></summary>

### Why
Currently, only errors are logged. No visibility on normal requests.

### Log format

```
[2026-03-21 14:32:00] INFO: GET /admin/users 200 12ms 2.1MB req_abc123 user=admin@fc.tv
[2026-03-21 14:32:01] INFO: POST /admin/users 201 45ms 3.2MB req_def456 user=admin@fc.tv
[2026-03-21 14:32:02] WARN: GET /admin/users/999 404 3ms 1.1MB req_ghi789 user=admin@fc.tv
```

### What it captures

| Field | Source |
|-------|--------|
| Timestamp | `date()` |
| Method + URI | `Request` |
| Status code | `Response` |
| Duration (ms) | `microtime` start/end |
| Peak memory | `memory_get_peak_usage()` |
| Request ID | generated at request start (short UUID) |
| User | `Auth::user()` if authenticated |
| IP | `$_SERVER['REMOTE_ADDR']` |

### Files to create

```
src/Middleware/RequestLogMiddleware.php
```

### Behavior
- **Dev**: log all requests to stderr
- **Prod**: log to `var/logs/access.log` (rotating, 14 days)
- **Slow requests** (> 500ms): automatic WARNING level

</details>

---

<details>
<summary><h2>1.6 — Tests (60%+ coverage)</h2></summary>

### Current state
4 test files, ~20 tests. Estimated coverage ~5-10%.

### Test plan

| Priority | File | Target | Tests |
|----------|---------|-------|-------|
| P0 | `ModelTest.php` | ORM CRUD | find, create, update, delete, softDelete, restore, relations |
| P0 | `QueryBuilderTest.php` | SQL builder | where, orderBy, limit, join, paginate, transaction |
| P0 | `ValidatorTest.php` | DTOs | Required, Email, Min, Max, MinLength, Regex, errors |
| P0 | `MiddlewareTest.php` | Pipeline | AuthMiddleware roles, CorsMiddleware headers |
| P1 | `EventDispatcherTest.php` | Events | listen, dispatch, once, hasListeners, priority |
| P1 | `ContainerTest.php` | DI | singleton, bind, get, auto resolution, exception |
| P1 | `DatabaseTest.php` | Connections | multi-DB, transaction, raw, PDO errors |
| P2 | `ProfilerTest.php` | Profiler | collection, panels, storage, ring buffer |
| P2 | `RateLimiterTest.php` | Rate limit | counting, reset, headers, 429 |
| P2 | `SchedulerTest.php` | Scheduler | Redis lock, execution, skip if locked |

### Goal
- **Phase 1**: P0 = core coverage (ORM, Validator, Middleware)
- **Phase 2**: P1 = feature coverage (Events, DI, DB)
- **Phase 3**: P2 = new feature coverage

</details>

---

# PHASE 2 — Differentiation

> Goal: features that competitors don't have (or do poorly).

---

<details>
<summary><h2>2.1 — Scheduler + Redis Lock (K8s-safe)</h2></summary>

### Why
Laravel needs external cron + Octane. Symfony needs Messenger. In K8s, an in-worker scheduler causes multiple executions.

### Solution: Redis Lock

```
+----------------------------------------------------------+
|  Pod 1 (worker)     Pod 2 (worker)     Pod 3 (worker)   |
|  Scheduler loop     Scheduler loop     Scheduler loop    |
|       |                  |                  |            |
|       v                  v                  v            |
|   +--------+        +--------+        +--------+       |
|   | Redis  |        | Redis  |        | Redis  |       |
|   | SETNX  |  OK    | SETNX  |  FAIL  | SETNX  | FAIL |
|   | lock:  |        | lock:  |        | lock:  |       |
|   | task1  |        | task1  |        | task1  |       |
|   +--------+        +--------+        +--------+       |
|       |                                                  |
|       v                                                  |
|   Execute task                                           |
|   (only once)                                            |
+----------------------------------------------------------+
```

### Target API

```php
// app/Schedule.php
return function (Scheduler $scheduler) {

    $scheduler->command('cache:clear')
              ->daily()
              ->at('03:00')
              ->name('daily-cache-clear');

    $scheduler->call(fn() => DB::raw('DELETE FROM logs WHERE created_at < NOW() - INTERVAL \'30 days\''))
              ->everyHour()
              ->name('clean-old-logs');

    $scheduler->call([ReportService::class, 'generate'])
              ->weekdays()
              ->at('08:00')
              ->name('weekly-report')
              ->withoutOverlapping(ttl: 3600);
};
```

### Available frequencies

| Method | Cron equivalent |
|---------|----------------|
| `->everyMinute()` | `* * * * *` |
| `->everyFiveMinutes()` | `*/5 * * * *` |
| `->everyFifteenMinutes()` | `*/15 * * * *` |
| `->everyThirtyMinutes()` | `*/30 * * * *` |
| `->hourly()` | `0 * * * *` |
| `->daily()` | `0 0 * * *` |
| `->dailyAt('13:00')` | `0 13 * * *` |
| `->weekly()` | `0 0 * * 0` |
| `->weekdays()` | `0 0 * * 1-5` |
| `->monthly()` | `0 0 1 * *` |
| `->cron('*/10 * * * *')` | Custom |

### Redis lock mechanism

```php
// Lock pseudo-code
$lockKey = "scheduler:lock:{$task->name}";
$acquired = $redis->set($lockKey, $podId, ['NX', 'EX' => $task->ttl]);

if ($acquired) {
    try {
        $task->execute();
    } finally {
        $redis->del($lockKey);
    }
}
// If not acquired -> another pod is already executing it, skip
```

### Files to create

```
src/Core/Scheduler/
├── Scheduler.php              <- task registry + loop
├── ScheduledTask.php          <- task definition (frequency, callback, name)
├── CronExpression.php         <- cron expression parser
├── Lock/
│   ├── LockInterface.php
│   ├── RedisLock.php          <- SETNX + TTL
│   └── InMemoryLock.php       <- for dev (single process)
src/Commands/
└── ScheduleRunCommand.php     <- ./forge schedule:run (one-shot)
                                  or integrated in worker loop
```

### Worker Integration

```php
// In App::runWorker()
$scheduler = new Scheduler($redis);
$scheduler->loadFrom('app/Schedule.php');

while ($request = frankenphp_handle_request()) {
    $this->handleRequest($request);

    // Tick scheduler every 60s
    if ($scheduler->shouldTick()) {
        $scheduler->tick();  // Execute due tasks with Redis lock
    }
}
```

</details>

---

<details>
<summary><h2>2.2 — Job Queue CLI Worker</h2></summary>

### Why
The event system has Redis/Database brokers but no CLI worker to consume async jobs. No retry, no failure handling.

### Architecture

```
+----------------------------------------------------------+
|                                                          |
|  App (HTTP)                    Worker (CLI)              |
|  +----------+                  +--------------+         |
|  | dispatch |---> Redis/DB --->| queue:work   |         |
|  | Job      |    (queue)       | consume      |         |
|  +----------+                  | retry        |         |
|                                | fail -> table |         |
|                                +--------------+         |
|                                                          |
+----------------------------------------------------------+
```

### Target API

```php
// Dispatch a job
Job::dispatch(SendWelcomeEmail::class, ['user_id' => 123]);
Job::dispatch(GenerateReport::class, ['month' => '2026-03'])->delay(60);

// Define a job
class SendWelcomeEmail implements JobInterface
{
    public int $maxRetries = 3;
    public int $retryDelay = 60;  // seconds between retries
    public int $timeout = 120;     // max execution seconds

    public function handle(array $payload): void
    {
        $user = User::findOrFail($payload['user_id']);
        // send the email...
    }

    public function failed(array $payload, \Throwable $e): void
    {
        // log, notify, etc.
    }
}
```

### CLI

```bash
./forge queue:work                    # consume the default queue
./forge queue:work --queue=emails     # specific queue
./forge queue:work --max-jobs=100     # stop after 100 jobs
./forge queue:work --timeout=3600     # stop after 1h
./forge queue:retry {job_id}          # re-execute a failed job
./forge queue:flush                   # clear failed jobs
./forge queue:status                  # stats (pending, processing, failed)
```

### Failed jobs table

```sql
CREATE TABLE failed_jobs (
    id SERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    job_class VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL,
    exception TEXT NOT NULL,
    attempts INT DEFAULT 0,
    failed_at TIMESTAMP DEFAULT NOW()
);
```

### Files to create

```
src/Core/Queue/
├── Job.php                    <- static facade Job::dispatch()
├── JobInterface.php           <- interface for jobs
├── QueueManager.php           <- dispatch + consume
├── Worker.php                 <- consumption loop
├── FailedJobHandler.php       <- log to failed_jobs table
src/Commands/
├── QueueWorkCommand.php       <- ./forge queue:work
├── QueueRetryCommand.php      <- ./forge queue:retry
├── QueueStatusCommand.php     <- ./forge queue:status
```

</details>

---

<details>
<summary><h2>2.3 — Feature Flags</h2></summary>

### Why
Progressive rollout, A/B testing, emergency disable — without redeploy.

### Target API

```php
// Check a flag
if (Feature::active('new-search-api')) {
    return $this->newSearch($query);
}

// Flag per user/role
if (Feature::for($user)->active('beta-dashboard')) { }

// Flag with percentage (progressive rollout)
Feature::define('new-checkout', fn($user) => $user->id % 100 < 20);  // 20% of users

// Simple on/off flag (stored in DB or Redis)
Feature::activate('maintenance-mode');
Feature::deactivate('maintenance-mode');

// Middleware
$router->get('/v2/search', [SearchController::class, 'v2'], [
    [FeatureFlagMiddleware::class, ['new-search-api']],
]);
```

### Storage

| Backend | Usage |
|---------|-------|
| **Database** (`feature_flags` table) | Persistent, editable via admin |
| **Redis** | Fast, cache for DB flags |
| **In-Memory** | Defined in code, no DB |

### Files to create

```
src/Core/Feature/
├── Feature.php                  <- static facade
├── FeatureManager.php           <- resolution logic
├── Storage/
│   ├── StorageInterface.php
│   ├── DatabaseStorage.php
│   ├── RedisStorage.php
│   └── InMemoryStorage.php
src/Middleware/
└── FeatureFlagMiddleware.php
src/Commands/
├── FeatureListCommand.php       <- ./forge feature:list
├── FeatureToggleCommand.php     <- ./forge feature:on new-search
```

</details>

---

<details>
<summary><h2>2.4 — State Machine</h2></summary>

### Why
Entities with lifecycle (orders, tickets, invoices) need controlled transitions. Avoids spaghetti `if/else` and invalid states.

### Target API

```php
// Definition
#[StateMachine([
    'draft'     => ['submit'],
    'submitted' => ['approve', 'reject'],
    'approved'  => ['ship'],
    'rejected'  => ['submit'],      // re-submission possible
    'shipped'   => ['deliver'],
    'delivered' => [],               // final state
])]
class Order extends Model
{
    protected static string $stateColumn = 'status';
}

// Usage
$order = Order::find(1);
$order->transition('submit');    // draft -> submitted OK
$order->transition('ship');      // submitted -> shipped FAIL InvalidTransitionException

// Check if a transition is possible
$order->canTransition('approve');  // true/false

// List available transitions
$order->availableTransitions();    // ['approve', 'reject']
```

### Automatic events

```php
// Each transition dispatches an event
Event::listen('order.submitted', fn($order) => /* notify the manager */);
Event::listen('order.approved', fn($order) => /* generate the invoice */);
Event::listen('order.shipped', fn($order) => /* send tracking info */);
```

### Files to create

```
src/Core/StateMachine/
├── StateMachine.php             <- transition logic
├── InvalidTransitionException.php
src/Attributes/
└── StateMachine.php             <- #[StateMachine([...])] attribute
```

</details>

---

<details>
<summary><h2>2.5 — Multi-Channel Notifications</h2></summary>

### Target API

```php
// Send a notification
Notification::send($user, new OrderShippedNotification($order));

// Definition
class OrderShippedNotification implements NotificationInterface
{
    public function via(mixed $notifiable): array
    {
        return ['email', 'slack', 'database'];
    }

    public function toEmail(mixed $notifiable): EmailMessage
    {
        return (new EmailMessage)
            ->to($notifiable->email)
            ->subject('Order shipped')
            ->body("Your order #{$this->order->id} is on its way.");
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->channel('#orders')
            ->text("Order #{$this->order->id} shipped.");
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['order_id' => $this->order->id, 'type' => 'shipped'];
    }
}
```

### Channels

| Channel | Transport | Config |
|-------|-----------|--------|
| `email` | SMTP / Mailgun / SES | `MAIL_DSN=smtp://...` |
| `slack` | Webhook | `SLACK_WEBHOOK_URL=...` |
| `database` | `notifications` table | No external dep |
| `sms` | Twilio / Vonage | `SMS_DSN=twilio://...` |

### Files to create

```
src/Core/Notification/
├── Notification.php               <- static facade
├── NotificationInterface.php
├── NotificationManager.php        <- routing to channels
├── Channel/
│   ├── ChannelInterface.php
│   ├── EmailChannel.php
│   ├── SlackChannel.php
│   ├── DatabaseChannel.php
│   └── SmsChannel.php
├── Message/
│   ├── EmailMessage.php
│   └── SlackMessage.php
```

</details>

---

<details>
<summary><h2>2.6 — Full-Text Search PostgreSQL</h2></summary>

### Why
PostgreSQL has an excellent native full-text engine. No need for Meilisearch or Algolia for 90% of cases.

### Target API

```php
// Simple search
$users = User::search('jean dupont')->get();

// With filters
$users = User::search('admin')
    ->where('active', true)
    ->orderBy('created_at', 'DESC')
    ->paginate(20);
```

### Implementation

```sql
-- GIN index on searchable columns
CREATE INDEX idx_users_search ON users
USING GIN (to_tsvector('french', coalesce(name,'') || ' ' || coalesce(email,'')));

-- Generated query
SELECT * FROM users
WHERE to_tsvector('french', coalesce(name,'') || ' ' || coalesce(email,''))
   @@ plainto_tsquery('french', $1)
ORDER BY ts_rank(...) DESC;
```

### Searchable Trait

```php
#[Table('users')]
class User extends Model
{
    use Searchable;

    protected static array $searchable = ['name', 'email'];
    protected static string $searchLanguage = 'french';
}
```

</details>

---

# PHASE 3 — DX & Ops

> Goal: developer experience and production operations.

---

<details>
<summary><h2>3.1 — docker-compose.yml</h2></summary>

```yaml
services:
  api:
    build:
      context: .
      dockerfile: Dockerfile.frankenphp
    ports:
      - "8080:8080"
    volumes:
      - .:/app
    env_file: .env
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: fennectra
      POSTGRES_USER: fennectra
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U fennectra"]
      interval: 5s
      timeout: 3s

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s

volumes:
  pgdata:
```

### Command

```bash
docker compose up -d     # start everything
docker compose logs -f   # follow logs
```

</details>

---

<details>
<summary><h2>3.2 — Migrations</h2></summary>

### Target API

```bash
./forge migrate                      # apply pending migrations
./forge migrate:create add_phone     # create an empty migration
./forge migrate:rollback             # rollback the last migration
./forge migrate:status               # view migration status
./forge migrate:fresh                # drop everything + re-migrate (dev)
```

### Structure

```
database/
└── migrations/
    ├── 20260321_001_create_users_table.sql
    ├── 20260321_002_create_roles_table.sql
    └── 20260322_001_add_phone_to_users.sql
```

### Tracking table

```sql
CREATE TABLE migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT NOW()
);
```

### File format

```sql
-- UP
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- DOWN
ALTER TABLE users DROP COLUMN phone;
```

</details>

---

<details>
<summary><h2>3.3 — Seeding</h2></summary>

```bash
./forge seed                         # run all seeders
./forge seed --class=UserSeeder      # a specific seeder
./forge make:seeder UserSeeder       # generate a seeder
```

```php
class UserSeeder implements SeederInterface
{
    public function run(): void
    {
        $roles = Role::all();

        for ($i = 0; $i < 50; $i++) {
            User::create([
                'name'    => Faker::name(),
                'email'   => Faker::email(),
                'role_id' => $roles->random()->id,
                'active'  => Faker::boolean(80),
            ]);
        }
    }
}
```

</details>

---

<details>
<summary><h2>3.4 — Readiness / Liveness Probes</h2></summary>

### Endpoints

```
GET /health          <- liveness (the process is running)
GET /health/ready    <- readiness (DB + Redis OK)
```

### Readiness check

```php
public function ready(): array
{
    $checks = [];

    // PostgreSQL
    try {
        DB::raw('SELECT 1');
        $checks['postgres'] = 'ok';
    } catch (\Throwable $e) {
        $checks['postgres'] = 'fail: ' . $e->getMessage();
    }

    // Redis
    try {
        $redis->ping();
        $checks['redis'] = 'ok';
    } catch (\Throwable $e) {
        $checks['redis'] = 'fail: ' . $e->getMessage();
    }

    $allOk = !in_array('fail', array_map(fn($v) => str_starts_with($v, 'fail') ? 'fail' : 'ok', $checks));

    return Response::json([
        'status' => $allOk ? 'ready' : 'degraded',
        'checks' => $checks,
    ], $allOk ? 200 : 503);
}
```

### K8s manifest

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 8080
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /health/ready
    port: 8080
  periodSeconds: 5
  failureThreshold: 3
```

</details>

---

<details>
<summary><h2>3.5 — Redis Cache</h2></summary>

```php
// Simple API
Cache::get('user:123');
Cache::set('user:123', $user, ttl: 3600);
Cache::forget('user:123');
Cache::has('user:123');

// Tags (group invalidation)
Cache::tags(['users'])->set("user:{$id}", $user, 3600);
Cache::tags(['users'])->flush();  // invalidate the entire group

// Remember pattern
$user = Cache::remember("user:{$id}", 3600, fn() => User::find($id));
```

### Files

```
src/Core/Cache/
├── CacheInterface.php
├── RedisCache.php           <- production
├── FileCache.php            <- already existing
└── NullCache.php            <- tests
```

</details>

---

<details>
<summary><h2>3.6 — API Versioning</h2></summary>

### Strategy: URL prefix

```php
// app/Routes/v1/user.php
$router->group(['prefix' => '/v1'], function ($router) {
    $router->get('/users', [UserControllerV1::class, 'index']);
});

// app/Routes/v2/user.php
$router->group(['prefix' => '/v2'], function ($router) {
    $router->get('/users', [UserControllerV2::class, 'index']);
});
```

### Auto-loading by version

```
app/Routes/
├── v1/
│   ├── user.php
│   └── admin.php
├── v2/
│   └── user.php           <- only modified endpoints
└── public.php              <- non-versioned routes (/health, /docs)
```

</details>

---

# PHASE 4 — Ecosystem

> Goal: advanced features to approach a full-stack framework.

---

<details>
<summary><h2>4.1 — Broadcasting SSE (Server-Sent Events)</h2></summary>

```php
// Broadcast an event to connected clients
Event::broadcast('order.shipped', $order);

// Client (JavaScript)
const es = new EventSource('/events?channel=orders');
es.onmessage = (e) => console.log(JSON.parse(e.data));
```

Natural integration with the existing EventDispatcher.

</details>

---

<details>
<summary><h2>4.2 — OAuth / Socialite</h2></summary>

```php
// Redirect to the provider
$router->get('/auth/google', fn() => OAuth::driver('google')->redirect());

// Callback
$router->get('/auth/google/callback', function () {
    $socialUser = OAuth::driver('google')->user();
    $user = User::firstOrCreate(['email' => $socialUser->email]);
    return ['token' => $jwt->generate($user)];
});
```

Drivers: Google, GitHub, Facebook, Microsoft.

</details>

---

<details>
<summary><h2>4.3 — Deploy CLI</h2></summary>

```bash
./forge deploy              # build + push + K8s rollout
./forge deploy --dry-run    # see what would be done
./forge deploy:rollback     # revert to previous version
./forge deploy:status       # rollout status
```

</details>

---

## Summary

```
PHASE 1 ---- Robustness ---------------------- 3-4 weeks
  1.1  Debug Profiler                          |||||||||| critical
  1.2  Eager Loading with()                    |||||||||| critical
  1.3  Rate Limiting                           ||||||||-- important
  1.4  Security Headers                        ||||||---- quick
  1.5  Request Logging                         ||||||---- quick
  1.6  Tests 60%+                              ||||||||-- continuous

PHASE 2 ---- Differentiation ----------------- 4-6 weeks
  2.1  Scheduler + Redis Lock                  |||||||||| unique
  2.2  Job Queue CLI Worker                    ||||||||-- important
  2.3  Feature Flags                           ||||||---- medium
  2.4  State Machine                           ||||||---- medium
  2.5  Multi-Channel Notifications             ||||||||-- important
  2.6  Full-Text Search                        ||||------ bonus

PHASE 3 ---- DX & Ops ----------------------- 2-3 weeks
  3.1  docker-compose.yml                      ||||||||-- quick
  3.2  Migrations                              |||||||||| critical
  3.3  Seeding                                 ||||||---- medium
  3.4  Readiness/Liveness Probes               ||||||||-- quick
  3.5  Redis Cache                             ||||||||-- important
  3.6  API Versioning                          ||||------ bonus

PHASE 4 ---- Ecosystem ---------------------- long term
  4.1  Broadcasting SSE
  4.2  OAuth / Socialite
  4.3  Deploy CLI
```
