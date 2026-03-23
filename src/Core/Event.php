<?php

namespace Fennec\Core;

/**
 * Facade statique pour l'Event Dispatcher.
 *
 * Usage :
 *   Event::listen('user.created', fn($user) => log($user));
 *   Event::dispatch('user.created', $user);
 *   Event::dispatch(new UserCreated($user));
 */
class Event
{
    /**
     * Enregistre un listener.
     */
    public static function listen(string $event, callable $listener, int $priority = 0): void
    {
        self::dispatcher()->listen($event, $listener, $priority);
    }

    /**
     * Dispatch un événement.
     */
    public static function dispatch(string|object $event, mixed $payload = null): void
    {
        self::dispatcher()->dispatch($event, $payload);
    }

    /**
     * Listener one-shot.
     */
    public static function once(string $event, callable $listener, int $priority = 0): void
    {
        self::dispatcher()->once($event, $listener, $priority);
    }

    /**
     * Vérifie si un événement a des listeners.
     */
    public static function hasListeners(string $event): bool
    {
        return self::dispatcher()->hasListeners($event);
    }

    /**
     * Supprime les listeners.
     */
    public static function forget(?string $event = null): void
    {
        self::dispatcher()->forget($event);
    }

    private static function dispatcher(): EventDispatcher
    {
        try {
            return Container::getInstance()->get(EventDispatcher::class);
        } catch (\Throwable) {
            return EventDispatcher::getInstance();
        }
    }
}
