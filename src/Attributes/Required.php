<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Required
{
    public function __construct(
        public string $message = '',
    ) {
    }
}
