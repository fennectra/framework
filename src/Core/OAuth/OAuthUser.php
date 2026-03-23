<?php

namespace Fennec\Core\OAuth;

/**
 * Value object representant un utilisateur OAuth.
 */
class OAuthUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
        public readonly ?string $avatar = null,
        public readonly string $provider = '',
        public readonly array $raw = [],
    ) {
    }
}
