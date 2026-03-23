<?php

namespace Fennec\Core;

use Fennec\Core\Event\DatabaseBroker;
use Fennec\Core\Event\EventBrokerInterface;
use Fennec\Core\Event\RedisBroker;
use Fennec\Core\Event\SyncBroker;

class EventDispatcher
{
    private static ?self $instance = null;
    private EventBrokerInterface $broker;

    /** @var array<string, bool> Directories deja scannes (guard contre re-discovery). */
    private array $discoveredDirs = [];

    public function __construct(?EventBrokerInterface $broker = null)
    {
        $this->broker = $broker ?? new SyncBroker();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(self $dispatcher): void
    {
        self::$instance = $dispatcher;
    }

    /**
     * Crée un EventDispatcher avec le broker configuré dans EVENT_BROKER.
     *
     * @param string $driver sync|redis|database
     */
    public static function withDriver(string $driver): self
    {
        $broker = match ($driver) {
            'redis' => RedisBroker::fromEnv(),
            'database', 'db' => DatabaseBroker::fromEnv(),
            default => new SyncBroker(),
        };

        return new self($broker);
    }

    /**
     * Retourne le broker actif.
     */
    public function broker(): EventBrokerInterface
    {
        return $this->broker;
    }

    /**
     * Enregistre un listener pour un événement.
     *
     * @param string   $event    Nom de l'événement (ex: 'user.created', UserCreated::class)
     * @param callable $listener Callback : fn($payload) => void
     * @param int      $priority Plus petit = exécuté en premier (défaut: 0)
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        $this->syncBroker()->addListener($event, $listener, $priority);
    }

    /**
     * Dispatch un événement à tous ses listeners + publie vers le broker.
     *
     * @param string|object $event   Nom string ou instance d'event class
     * @param mixed         $payload Données associées (ignoré si $event est un objet)
     */
    public function dispatch(string|object $event, mixed $payload = null): void
    {
        if (is_object($event)) {
            $eventName = $event::class;
            $payload = $event;
        } else {
            $eventName = $event;
        }

        $start = microtime(true);

        // Le broker gère tout : sync exécute directement, redis/database
        // exécutent le sync interne puis publient vers le transport externe
        $this->broker->publish($eventName, $payload);

        if (class_exists(\Fennec\Core\Profiler\Profiler::class, false)) {
            $profiler = \Fennec\Core\Profiler\Profiler::getInstance();
            $profiler?->addEvent($eventName, (microtime(true) - $start) * 1000);
        }
    }

    /**
     * Vérifie si un événement a des listeners.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->syncBroker()->getListeners($event));
    }

    /**
     * Retourne le nombre de listeners pour un événement.
     */
    public function listenerCount(string $event): int
    {
        return count($this->syncBroker()->getListeners($event));
    }

    /**
     * Supprime tous les listeners d'un événement (ou tous si null).
     */
    public function forget(?string $event = null): void
    {
        $this->syncBroker()->removeListeners($event);
    }

    /**
     * Enregistre un listener qui s'exécute une seule fois.
     */
    public function once(string $event, callable $listener, int $priority = 0): void
    {
        $syncBroker = $this->syncBroker();
        $wrapper = function (mixed $payload) use ($event, $listener, $syncBroker) {
            $listener($payload);
            $syncBroker->removeListeners($event);
        };

        $this->listen($event, $wrapper, $priority);
    }

    /**
     * Découvre et enregistre les listeners d'une classe annotée #[Listener].
     *
     * Protege contre les appels multiples pour un meme repertoire (worker-safe).
     */
    public function discoverListeners(string $directory): void
    {
        $resolved = realpath($directory) ?: $directory;

        if (isset($this->discoveredDirs[$resolved])) {
            return;
        }

        $this->discoveredDirs[$resolved] = true;

        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*.php') as $file) {
            $className = $this->resolveClassName($file);
            if ($className === null || !class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);
            $attrs = $ref->getAttributes(\Fennec\Attributes\Listener::class);

            if (empty($attrs)) {
                continue;
            }

            $listenerAttr = $attrs[0]->newInstance();
            $instance = new $className();

            $this->listen($listenerAttr->event, [$instance, 'handle'], $listenerAttr->priority);
        }
    }

    /**
     * Retourne le nom du driver actif.
     */
    public function driverName(): string
    {
        return $this->broker->driver();
    }

    /**
     * Accède au SyncBroker interne (pour les listeners locaux).
     */
    private function syncBroker(): SyncBroker
    {
        if ($this->broker instanceof SyncBroker) {
            return $this->broker;
        }

        // Redis et Database encapsulent un SyncBroker
        if (method_exists($this->broker, 'sync')) {
            return $this->broker->sync();
        }

        // Fallback — ne devrait pas arriver
        return new SyncBroker();
    }

    private function resolveClassName(string $file): ?string
    {
        $content = file_get_contents($file);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+(.+?);/', $content, $m)) {
            $namespace = $m[1];
        }
        if (preg_match('/class\s+(\w+)/', $content, $m)) {
            $class = $m[1];
        }

        if (empty($class)) {
            return null;
        }

        return $namespace ? "{$namespace}\\{$class}" : $class;
    }
}
