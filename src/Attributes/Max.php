<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Max
{
    public function __construct(
        public int|float $max,
        public string $message = '',
    ) {
    }
}
