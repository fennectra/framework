<?php

namespace Fennec\Core;

class MiddlewarePipeline
{
    /** @var array<array{class: string, params: mixed}> */
    private array $middlewares = [];

    /** @var array<string, object> Cache des middlewares sans params (stateless) */
    private static array $instanceCache = [];

    public function __construct(
        private ?Container $container = null,
    ) {
    }

    /**
     * Ajoute un middleware au pipeline.
     */
    public function pipe(string $class, mixed $params = null): self
    {
        $this->middlewares[] = ['class' => $class, 'params' => $params];

        return $this;
    }

    /**
     * Exécute le pipeline avec un handler final.
     */
    public function run(Request $request, callable $core): mixed
    {
        $next = $core;

        foreach (array_reverse($this->middlewares) as $mw) {
            $next = function (Request $req) use ($mw, $next) {
                $instance = $this->resolveMiddleware($mw['class'], $mw['params']);
                $start = microtime(true);

                // Nouveau style : MiddlewareInterface
                if ($instance instanceof MiddlewareInterface) {
                    $result = $instance->handle($req, $next);
                } elseif (method_exists($instance, 'handle')) {
                    // Ancien style : handle($params) — backward compat
                    $instance->handle($mw['params']);
                    $result = $next($req);
                } else {
                    throw new ContainerException("Middleware invalide : {$mw['class']}");
                }

                if (class_exists(\Fennec\Core\Profiler\Profiler::class, false)) {
                    $profiler = \Fennec\Core\Profiler\Profiler::getInstance();
                    $profiler?->addMiddleware($mw['class'], (microtime(true) - $start) * 1000);
                }

                return $result;
            };
        }

        return $next($request);
    }

    private function resolveMiddleware(string $class, mixed $params): object
    {
        // Middlewares avec params (ex: Auth avec roles) — toujours re-instancies
        if ($params !== null) {
            if ($this->container && is_subclass_of($class, MiddlewareInterface::class)) {
                $args = is_array($params) && !array_is_list($params)
                    ? $params
                    : ['roles' => $params];

                return $this->container->make($class, $args);
            }

            return new $class();
        }

        // Middlewares sans params (stateless) — caches entre les requetes (worker-safe)
        if (isset(self::$instanceCache[$class])) {
            return self::$instanceCache[$class];
        }

        $instance = $this->container
            ? $this->container->get($class)
            : new $class();

        self::$instanceCache[$class] = $instance;

        return $instance;
    }
}
