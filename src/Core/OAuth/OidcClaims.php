<?php

namespace Fennec\Core\OAuth;

/**
 * Value object representing standardized OpenID Connect claims.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims
 */
class OidcClaims
{
    public function __construct(
        public readonly string $sub,
        public readonly ?string $email = null,
        public readonly ?bool $emailVerified = null,
        public readonly ?string $name = null,
        public readonly ?string $givenName = null,
        public readonly ?string $familyName = null,
        public readonly ?string $picture = null,
        public readonly ?string $locale = null,
        public readonly ?string $nonce = null,
        public readonly ?string $issuer = null,
        public readonly ?string $audience = null,
        public readonly ?int $issuedAt = null,
        public readonly ?int $expiresAt = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build from decoded id_token claims array.
     *
     * @param array<string, mixed> $claims
     */
    public static function fromArray(array $claims): self
    {
        return new self(
            sub: (string) ($claims['sub'] ?? ''),
            email: $claims['email'] ?? null,
            emailVerified: isset($claims['email_verified']) ? (bool) $claims['email_verified'] : null,
            name: $claims['name'] ?? null,
            givenName: $claims['given_name'] ?? null,
            familyName: $claims['family_name'] ?? null,
            picture: $claims['picture'] ?? null,
            locale: $claims['locale'] ?? null,
            nonce: $claims['nonce'] ?? null,
            issuer: $claims['iss'] ?? null,
            audience: is_array($claims['aud'] ?? null) ? ($claims['aud'][0] ?? null) : ($claims['aud'] ?? null),
            issuedAt: isset($claims['iat']) ? (int) $claims['iat'] : null,
            expiresAt: isset($claims['exp']) ? (int) $claims['exp'] : null,
            raw: $claims,
        );
    }

    /**
     * Convert claims to OAuthUser for unified handling.
     */
    public function toOAuthUser(string $provider): OAuthUser
    {
        return new OAuthUser(
            id: $this->sub,
            email: $this->email,
            name: $this->name,
            avatar: $this->picture,
            provider: $provider,
            raw: $this->raw,
        );
    }
}
