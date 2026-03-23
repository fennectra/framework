<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiStatus
{
    public function __construct(
        public int $code,
        public string $description,
    ) {
    }
}
