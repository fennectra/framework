<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Min
{
    public function __construct(
        public int|float $min,
        public string $message = '',
    ) {
    }
}
