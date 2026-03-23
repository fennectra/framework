<?php

namespace Fennec\Core;

class App
{
    private Container $container;
    private Router $router;

    public function __construct()
    {
        // Gestion globale des erreurs
        $errorHandler = new ErrorHandler();
        $errorHandler->register();

        // Container DI
        $this->container = new Container();

        // Multi-tenancy
        $tenantManager = new TenantManager();
        $tenantConfigPath = FENNEC_BASE_PATH . '/app/config/tenants.php';
        $tenantManager->loadConfig($tenantConfigPath);
        $this->container->instance(TenantManager::class, $tenantManager);

        // Services core
        $this->container->singleton(DatabaseManager::class, function () use ($tenantManager) {
            $manager = new DatabaseManager();
            $manager->setTenantManager($tenantManager);

            return $manager;
        });
        $this->container->singleton(JwtService::class, fn () => new JwtService());

        // Event Dispatcher — lazy: filesystem scan deferred until first Event use
        $this->container->singleton(EventDispatcher::class, function () {
            $eventDriver = Env::get('EVENT_BROKER', 'sync');
            $dispatcher = EventDispatcher::withDriver($eventDriver);
            EventDispatcher::setInstance($dispatcher);

            // Auto-discovery des listeners applicatifs
            $listenersDir = dirname(__DIR__) . '/Listeners';
            $appListenersDir = FENNEC_BASE_PATH . '/app/Listeners';
            $dispatcher->discoverListeners($listenersDir);
            $dispatcher->discoverListeners($appListenersDir);

            return $dispatcher;
        });

        // Profiler — lazy: only needed when profiling is enabled
        $this->container->singleton(Profiler\Profiler::class, function () {
            $profilerEnabled = Env::get('PROFILER_ENABLED', Env::get('APP_ENV', 'prod') === 'dev' ? '1' : '0') === '1';
            $profiler = new Profiler\Profiler(new Profiler\InMemoryStorage(50), $profilerEnabled);
            Profiler\Profiler::setInstance($profiler);

            return $profiler;
        });

        // Storage — lazy: S3/GCS client only initialized on first file operation
        $this->container->singleton(Storage::class, function () {
            $storage = Storage::withDriver(Env::get('STORAGE_DRIVER', 'local'));
            Storage::setInstance($storage);

            return $storage;
        });

        // Image Transformer — lazy: GD init deferred until first image operation
        if (extension_loaded('gd')) {
            $this->container->singleton(Image\ImageTransformer::class, function () {
                $imageTransformer = new Image\ImageTransformer();
                Image\ImageTransformer::setInstance($imageTransformer);

                return $imageTransformer;
            });
        }

        // Webhook Manager — lazy: instantiation deferred until first access
        $this->container->singleton(Webhook\WebhookManager::class, function () {
            $webhookManager = new Webhook\WebhookManager();
            Webhook\WebhookManager::setInstance($webhookManager);

            return $webhookManager;
        });

        // Rate Limiter
        $this->container->singleton(RateLimiter::class, function () {
            if (extension_loaded('redis') && Env::get('REDIS_HOST')) {
                return new RateLimiter(RateLimiter\RedisStore::fromEnv());
            }

            return new RateLimiter(new RateLimiter\InMemoryStore());
        });

        // Router
        $this->router = new Router($this->container);

        // Fennec UI routes (super admin dashboard)
        if (Env::get('UI_ADMIN_EMAIL')) {
            Ui\UiRoutes::register($this->router);
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Charge les routes depuis un fichier ou un dossier.
     * Si $path est un dossier, charge tous les *.php dedans.
     */
    public function loadRoutes(string $path): void
    {
        $router = $this->router;

        if (is_dir($path)) {
            foreach (glob($path . '/*.php') as $routeFile) {
                require $routeFile;
            }
        } else {
            require $path;
        }
    }

    /**
     * Exécute le dispatch HTTP (mode classique).
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->router->dispatch($method, $uri);
    }

    /**
     * Mode worker FrankenPHP : l'app reste en mémoire, chaque requête
     * est traitée dans la boucle frankenphp_handle_request().
     *
     * Lifecycle :
     *   1. Boot (une seule fois) — init stats, logger, container
     *   2. beforeRequest() — snapshot memoire avant handler
     *   3. handler — dispatch HTTP (erreurs catchees, jamais de crash worker)
     *   4. afterRequest() — metriques, peak, delta, trend
     *   5. cleanup — DB flush, auth reset, GC
     *   6. Repeat ou shutdown (MAX_REQUESTS / signal)
     */
    public function runWorker(): void
    {
        // ── Boot : une seule fois, vit pour toute la duree du worker ──
        $stats = new WorkerStats();
        $this->container->instance(WorkerStats::class, $stats);

        $maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);

        // Scheduler integration (si app/Schedule.php existe)
        $scheduler = null;
        $schedule = null;
        $schedulePath = FENNEC_BASE_PATH . '/app/Schedule.php';
        if (file_exists($schedulePath) && Env::get('SCHEDULER_ENABLED', '0') === '1') {
            $schedule = require $schedulePath;
            if ($schedule instanceof \Fennec\Core\Scheduler\Schedule) {
                $lock = null;
                if (extension_loaded('redis') && Env::get('REDIS_HOST')) {
                    try {
                        $lock = new \Fennec\Core\Redis\RedisLock(
                            host: Env::get('REDIS_HOST', '127.0.0.1'),
                            port: (int) Env::get('REDIS_PORT', '6379'),
                            password: Env::get('REDIS_PASSWORD') ?: null,
                            db: (int) Env::get('REDIS_DB', '0'),
                            prefix: Env::get('REDIS_PREFIX', 'app:') . 'lock:',
                        );
                    } catch (\Throwable) {
                        // Redis indisponible : pas de lock
                    }
                }
                $scheduler = new \Fennec\Core\Scheduler\Scheduler($lock);
            }
        }

        Logger::info('Worker started', [
            'pid' => getmypid(),
            'max_requests' => $maxRequests,
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ]);

        // ── Handler : execute a chaque requete par FrankenPHP ──
        $handler = function () use ($stats): void {
            if (ob_get_level()) {
                ob_end_clean();
            }

            try {
                $this->run();
            } catch (HttpException $e) {
                // Erreurs HTTP attendues (404, 422, etc.) — pas un bug
                if (!headers_sent()) {
                    http_response_code($e->statusCode);
                    header('Content-Type: application/json');
                    $response = ['error' => $e->detail];
                    if ($e->errors) {
                        $response['errors'] = $e->errors;
                    }
                    echo json_encode($response);
                }
            } catch (\Throwable $e) {
                $stats->recordError($e);

                // Log l'erreur mais ne crash PAS le worker
                Logger::error('Request error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'pid' => getmypid(),
                ]);

                // Envoyer une reponse 500 propre si rien n'a ete envoye
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Internal Server Error']);
                }
            }
        };

        // ── Boucle worker ──
        for ($i = 0; !$maxRequests || $i < $maxRequests; ++$i) {
            // Snapshot memoire AVANT la requete
            $stats->beforeRequest();

            $keepRunning = \frankenphp_handle_request($handler);

            // Metriques APRES la requete (hors handler, toujours execute)
            $stats->afterRequest();

            // Scheduler tick (throttle 60s interne)
            if ($scheduler !== null && $schedule !== null) {
                try {
                    $scheduler->tick($schedule);
                } catch (\Throwable $e) {
                    Logger::error('Scheduler error', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            // Cleanup entre requetes — dans un try/finally pour GARANTIR l'execution
            // meme si flush() ou clearInstances() leve une exception
            try {
                $this->container->get(DatabaseManager::class)->flush();
                Database::clearInstances();
            } catch (\Throwable $e) {
                Logger::error('Cleanup error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            } finally {
                // Reset etat request-scoped — TOUJOURS execute
                $this->container->get(TenantManager::class)->reset();
                unset($_REQUEST['__auth_user']);

                // Vider tous les output buffers orphelins (niveaux imbriques)
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                \gc_collect_cycles();
            }

            if (!$keepRunning) {
                break;
            }
        }

        // ── Shutdown ──
        Logger::info('Worker stopping', [
            'pid' => getmypid(),
            'requests_handled' => $stats->getSnapshot()['worker']['requests_handled'],
            'reason' => $maxRequests ? 'max_requests_reached' : 'signal',
            'peak_memory_mb' => $stats->getSnapshot()['memory']['peak_mb'],
        ]);
    }
}
