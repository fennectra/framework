<?php

namespace Fennec\Core\OAuth;

/**
 * Value object representant un token OAuth.
 */
class OAuthToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresIn = null,
        public readonly string $tokenType = 'Bearer',
        public readonly ?string $idToken = null,
    ) {
    }
}
