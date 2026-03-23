<?php

namespace Fennec\Attributes;

/**
 * Marque une classe comme broadcastable sur un canal specifique.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Broadcast
{
    public function __construct(
        public string $channel,
    ) {
    }
}
