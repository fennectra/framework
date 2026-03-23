<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class RateLimit
{
    public int $windowSeconds;

    public function __construct(
        public int $limit = 60,
        string $window = 'minute',
    ) {
        $this->windowSeconds = match ($window) {
            'second' => 1,
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 60,
        };
    }
}
