<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class After
{
    public function __construct(
        public string $handler,
    ) {
    }
}
