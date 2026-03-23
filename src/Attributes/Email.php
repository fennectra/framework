<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Email
{
    public function __construct(
        public string $message = 'doit être une adresse email valide',
    ) {
    }
}
