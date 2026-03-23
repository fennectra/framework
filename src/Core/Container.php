<?php

namespace Fennec\Core;

class Container
{
    private static ?self $instance = null;

    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Enregistre un binding (factory).
     */
    public function bind(string $abstract, \Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->singletons[$abstract], $this->instances[$abstract]);
    }

    /**
     * Enregistre un singleton (instancié une seule fois).
     */
    public function singleton(string $abstract, \Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = true;
        unset($this->instances[$abstract]);
    }

    /**
     * Enregistre une instance déjà construite.
     */
    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
    }

    /**
     * Vérifie si un binding ou une instance existe.
     */
    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract]);
    }

    /**
     * Résout une dépendance (singleton-aware).
     */
    public function get(string $abstract): mixed
    {
        // Instance déjà résolue (singleton cache)
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $object = $this->resolve($abstract);

        if (class_exists(\Fennec\Core\Profiler\Profiler::class, false)) {
            $profiler = \Fennec\Core\Profiler\Profiler::getInstance();
            $profiler?->addResolution($abstract);
        }

        // Cache si singleton
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Crée toujours une nouvelle instance (ignore le cache singleton).
     */
    public function make(string $abstract, array $params = []): mixed
    {
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if ($concrete instanceof \Closure) {
                return $concrete($this, ...$params);
            }
            $abstract = $concrete;
        }

        return $this->autowire($abstract, $params);
    }

    /**
     * Résout via binding ou autowiring.
     */
    private function resolve(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if ($concrete instanceof \Closure) {
                return $concrete($this);
            }

            // String binding : résoudre la classe concrète
            return $this->autowire($concrete);
        }

        // Pas de binding → autowiring direct
        return $this->autowire($abstract);
    }

    /**
     * Autowiring : instancie une classe en résolvant ses dépendances constructeur.
     */
    private function autowire(string $className, array $extraParams = []): mixed
    {
        if (!class_exists($className)) {
            throw new ContainerException("Classe introuvable : {$className}");
        }

        $ref = new \ReflectionClass($className);

        if (!$ref->isInstantiable()) {
            throw new ContainerException("Classe non instanciable : {$className}");
        }

        $constructor = $ref->getConstructor();

        // Pas de constructeur → new simple
        if ($constructor === null) {
            return new $className();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // Paramètre fourni explicitement
            if (isset($extraParams[$name])) {
                $args[] = $extraParams[$name];
                continue;
            }

            $type = $param->getType();

            // Type classe → résolution récursive
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $args[] = $this->get($type->getName());
                    continue;
                } catch (ContainerException $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }

                    throw $e;
                }
            }

            // Valeur par défaut
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Nullable
            if ($type !== null && $type->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new ContainerException(
                "Impossible de résoudre le paramètre \${$name} de {$className}"
            );
        }

        return $ref->newInstanceArgs($args);
    }
}
