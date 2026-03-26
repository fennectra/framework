<?php

namespace Fennec\Core;

class Router
{
    private static ?self $current = null;
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];
    private string $groupDescription = '';
    private ?Container $container = null;
    private array $globalMiddleware = [];

    private static int $maxReflectionCacheSize = 200;

    /** @var array<string, \ReflectionMethod> */
    private static array $reflectionMethodCache = [];

    /** @var array<string, \ReflectionClass> */
    private static array $reflectionClassCache = [];

    private static function getReflectionMethod(string $class, string $method): \ReflectionMethod
    {
        $key = $class . '::' . $method;
        if (isset(self::$reflectionMethodCache[$key])) {
            // Move to end for LRU
            $value = self::$reflectionMethodCache[$key];
            unset(self::$reflectionMethodCache[$key]);
            self::$reflectionMethodCache[$key] = $value;

            return $value;
        }
        if (count(self::$reflectionMethodCache) >= self::$maxReflectionCacheSize) {
            // Evict oldest entry (first key)
            unset(self::$reflectionMethodCache[array_key_first(self::$reflectionMethodCache)]);
        }

        return self::$reflectionMethodCache[$key] = new \ReflectionMethod($class, $method);
    }

    private static function getReflectionClass(string $class): \ReflectionClass
    {
        if (isset(self::$reflectionClassCache[$class])) {
            $value = self::$reflectionClassCache[$class];
            unset(self::$reflectionClassCache[$class]);
            self::$reflectionClassCache[$class] = $value;

            return $value;
        }
        if (count(self::$reflectionClassCache) >= self::$maxReflectionCacheSize) {
            unset(self::$reflectionClassCache[array_key_first(self::$reflectionClassCache)]);
        }

        return self::$reflectionClassCache[$class] = new \ReflectionClass($class);
    }

    /**
     * Clear reflection caches — for worker memory safety.
     */
    public static function clearReflectionCache(): void
    {
        self::$reflectionMethodCache = [];
        self::$reflectionClassCache = [];
    }

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
        self::$current = $this;
    }

    /**
     * Ajoute un middleware global exécuté sur toutes les routes.
     */
    public function addGlobalMiddleware(string $class, mixed $params = null): void
    {
        $this->globalMiddleware[] = ['class' => $class, 'params' => $params];
    }

    public static function getCurrent(): ?self
    {
        return self::$current;
    }

    public function get(string $path, array $action, ?array $middleware = null): void
    {
        $this->addRoute('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, ?array $middleware = null): void
    {
        $this->addRoute('POST', $path, $action, $middleware);
    }

    public function put(string $path, array $action, ?array $middleware = null): void
    {
        $this->addRoute('PUT', $path, $action, $middleware);
    }

    public function delete(string $path, array $action, ?array $middleware = null): void
    {
        $this->addRoute('DELETE', $path, $action, $middleware);
    }

    public function patch(string $path, array $action, ?array $middleware = null): void
    {
        $this->addRoute('PATCH', $path, $action, $middleware);
    }

    /**
     * Groupe de routes avec prefix et/ou middleware communs.
     *
     * @param array    $options  ['prefix' => '/admin', 'middleware' => [...]]
     * @param callable $callback function($router) { $router->get(...); }
     */
    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousDescription = $this->groupDescription;

        $this->groupPrefix .= $options['prefix'] ?? '';
        $this->groupMiddleware = array_merge(
            $this->groupMiddleware,
            $options['middleware'] ?? []
        );
        $this->groupDescription = $options['description'] ?? $this->groupDescription;

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupDescription = $previousDescription;
    }

    private function addRoute(string $method, string $path, array $action, ?array $middleware): void
    {
        $fullPath = $this->groupPrefix . $path;
        $fullMiddleware = array_merge(
            $this->groupMiddleware,
            $middleware ?? []
        );

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'controller' => $action[0],
            'action' => $action[1],
            'middleware' => !empty($fullMiddleware) ? $fullMiddleware : null,
            'description' => $this->groupDescription ?: null,
        ];
    }

    /** @var array|null|false Routes compilees en cache (null = pas encore charge, false = pas de cache) */
    private static array|null|false $cachedRoutesStatic = null;

    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim($uri, '/') ?: '/';

        // Charger le cache UNE SEULE FOIS (persiste en mode worker)
        if (self::$cachedRoutesStatic === null) {
            $cacheDir = FENNEC_BASE_PATH . '/var/cache';
            if (is_dir($cacheDir)) {
                $routeCache = new Cache\RouteCache(new Cache\FileCache($cacheDir));
                self::$cachedRoutesStatic = $routeCache->load() ?? false;
            } else {
                self::$cachedRoutesStatic = false;
            }
        }

        $routesToMatch = self::$cachedRoutesStatic ?: $this->routes;

        // OPTIONS preflight — run global middleware (CORS) without route matching
        if ($method === 'OPTIONS') {
            $pipeline = new MiddlewarePipeline($this->container);
            foreach ($this->globalMiddleware as $gm) {
                $pipeline->pipe($gm['class'], $gm['params']);
            }
            $pipeline->run(new Request($method, $uri), fn () => null);

            return;
        }

        foreach ($routesToMatch as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $route['pattern'] ?? $this->convertToRegex($route['path']);

            if (preg_match($pattern, $uri, $matches)) {
                // Extraction des paramètres nommés (depuis l'URL)
                $urlParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Construction du pipeline de middlewares
                $pipeline = new MiddlewarePipeline($this->container);

                // Middlewares globaux
                foreach ($this->globalMiddleware as $gm) {
                    $pipeline->pipe($gm['class'], $gm['params']);
                }

                // Middlewares de la route
                if ($route['middleware']) {
                    foreach ($route['middleware'] as $middleware) {
                        if (is_array($middleware)) {
                            $pipeline->pipe($middleware[0], $middleware[1] ?? null);
                        } else {
                            $pipeline->pipe($middleware);
                        }
                    }
                }

                // Handler final : instanciation du controller et exécution
                $request = new Request($method, $uri);
                $pipeline->run($request, function (Request $req) use ($route, $urlParams) {
                    $controller = $this->container
                        ? $this->container->get($route['controller'])
                        : new $route['controller']();
                    $args = $this->resolveMethodArgs($route['controller'], $route['action'], $urlParams);

                    // Événements #[Before]
                    $this->fireEvents($route['controller'], $route['action'], $req, 'before');

                    $result = call_user_func_array([$controller, $route['action']], $args);

                    // Événements #[After]
                    $this->fireEvents($route['controller'], $route['action'], $req, 'after', $result);

                    if ($result !== null) {
                        $this->sendResponse($result);
                    }

                    return $result;
                });

                return;
            }
        }

        throw new HttpException(404, 'Route non trouvée');
    }

    private function resolveMethodArgs(string $controllerClass, string $action, array $urlParams): array
    {
        $ref = self::getReflectionMethod($controllerClass, $action);
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Paramètre URL (ex: {id})
            if (isset($urlParams[$name])) {
                $args[] = $urlParams[$name];
                continue;
            }

            // DTO : classe typée → hydrater depuis GET params, JSON ou form-urlencoded
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

                if ($requestMethod === 'GET') {
                    // Pour les GET, hydrater le DTO depuis les query parameters
                    $body = $_GET;
                } else {
                    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

                    if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                        $body = $_POST;
                    } else {
                        $body = json_decode(file_get_contents('php://input'), true);
                    }
                }

                if (!is_array($body)) {
                    throw new HttpException(400, 'Body JSON invalide');
                }

                // Validation : vérifier les champs requis et les types
                $errors = $this->validateDto($className, $body);
                if ($errors) {
                    throw new HttpException(422, 'Validation échouée', $errors);
                }

                // Filtrer le body pour ne garder que les paramètres du constructeur
                $dtoRef = self::getReflectionClass($className);
                $dtoConstructor = $dtoRef->getConstructor();
                if ($dtoConstructor) {
                    $allowedParams = array_map(fn ($p) => $p->getName(), $dtoConstructor->getParameters());
                    $body = array_intersect_key($body, array_flip($allowedParams));
                }

                $args[] = new $className(...$body);
                continue;
            }

            // Valeur par défaut ou null
            $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        return $args;
    }

    private function validateDto(string $className, array $data): array
    {
        return Validator::validate($className, $data);
    }

    private function sendResponse(mixed $result): void
    {
        if (is_object($result)) {
            // Convertir l'objet en array (propriétés publiques)
            $data = [];
            $ref = self::getReflectionClass($result::class);
            foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $data[$prop->getName()] = $prop->getValue($result);
            }
            Response::json($data);
        } elseif (is_array($result)) {
            Response::json($result);
        }
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    /**
     * Exécute les event handlers #[Before] ou #[After] d'une action.
     */
    private function fireEvents(string $controller, string $action, Request $request, string $type, mixed $result = null): void
    {
        $ref = self::getReflectionMethod($controller, $action);
        $attrClass = $type === 'before'
            ? \Fennec\Attributes\Before::class
            : \Fennec\Attributes\After::class;

        foreach ($ref->getAttributes($attrClass) as $attr) {
            $instance = $attr->newInstance();
            $handler = $this->container
                ? $this->container->get($instance->handler)
                : new ($instance->handler)();

            if ($handler instanceof EventHandlerInterface) {
                $handler->handle($request, $result);
            }
        }
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
