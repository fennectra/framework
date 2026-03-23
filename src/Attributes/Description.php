<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Description
{
    public function __construct(
        public string $value,
    ) {
    }
}
