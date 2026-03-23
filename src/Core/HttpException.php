<?php

namespace Fennec\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $detail,
        public readonly array $errors = [],
    ) {
        parent::__construct($detail, $statusCode);
    }
}
