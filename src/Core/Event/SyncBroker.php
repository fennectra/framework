<?php

namespace Fennec\Core\Event;

/**
 * Broker synchrone : exécute les listeners immédiatement dans le même process.
 * C'est le comportement par défaut — zéro dépendance externe.
 */
class SyncBroker implements EventBrokerInterface
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $listeners = [];

    public function publish(string $eventName, mixed $payload): void
    {
        $listeners = $this->listeners[$eventName] ?? [];

        usort($listeners, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($listeners as $listener) {
            $listener['callback']($payload);
        }
    }

    /**
     * Enregistre un listener local (sync uniquement).
     */
    public function addListener(string $event, callable $callback, int $priority = 0): void
    {
        $this->listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Retourne les listeners pour un événement.
     *
     * @return array<int, array{callback: callable, priority: int}>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Supprime les listeners d'un événement.
     */
    public function removeListeners(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
        } else {
            unset($this->listeners[$event]);
        }
    }

    public function driver(): string
    {
        return 'sync';
    }
}
