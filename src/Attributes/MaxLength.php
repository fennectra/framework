<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class MaxLength
{
    public function __construct(
        public int $max,
        public string $message = '',
    ) {
    }
}
