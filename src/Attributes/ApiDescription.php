<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiDescription
{
    public function __construct(
        public string $summary,
        public string $description = '',
    ) {
    }
}
