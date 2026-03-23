<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class MinLength
{
    public function __construct(
        public int $min,
        public string $message = '',
    ) {
    }
}
