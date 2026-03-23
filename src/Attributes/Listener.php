<?php

namespace Fennec\Attributes;

/**
 * Marque une classe comme listener d'un événement.
 *
 * Usage :
 *   #[Listener(UserCreated::class)]
 *   class SendWelcomeEmail {
 *       public function handle(UserCreated $event): void { ... }
 *   }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Listener
{
    public function __construct(
        public string $event,
        public int $priority = 0,
    ) {
    }
}
