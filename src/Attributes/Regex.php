<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Regex
{
    public function __construct(
        public string $pattern,
        public string $message = 'format invalide',
    ) {
    }
}
