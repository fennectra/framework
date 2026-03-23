<?php

namespace Fennec\Core\Event;

/**
 * Interface pour les brokers d'événements.
 *
 * Un broker reçoit les événements dispatchés et les transmet aux consumers.
 * Le broker "sync" exécute immédiatement, les autres (redis, database)
 * persistent pour un traitement asynchrone.
 */
interface EventBrokerInterface
{
    /**
     * Publie un événement vers le broker.
     *
     * @param string $eventName Nom de l'événement
     * @param mixed  $payload   Données sérialisables
     */
    public function publish(string $eventName, mixed $payload): void;

    /**
     * Retourne le nom du driver.
     */
    public function driver(): string;
}
