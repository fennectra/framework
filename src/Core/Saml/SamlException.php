<?php

namespace Fennec\Core\Saml;

/**
 * Exception thrown during SAML authentication operations.
 */
class SamlException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('[SAML] ' . $message, $code, $previous);
    }
}
