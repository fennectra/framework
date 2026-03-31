<?php

namespace Fennec\Core\Saml;

use Fennec\Core\OAuth\OAuthUser;

/**
 * Value object representing an authenticated SAML user.
 */
class SamlUser
{
    public function __construct(
        public readonly string $nameId,
        public readonly ?string $nameIdFormat = null,
        public readonly ?string $sessionIndex = null,
        public readonly ?string $email = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $displayName = null,
        /** @var array<string, string[]> All SAML attributes (key => values) */
        public readonly array $attributes = [],
    ) {
    }

    /**
     * Build from SAML assertion attributes.
     *
     * @param string                   $nameId
     * @param string|null              $nameIdFormat
     * @param string|null              $sessionIndex
     * @param array<string, string[]>  $attributes
     */
    public static function fromAssertion(
        string $nameId,
        ?string $nameIdFormat,
        ?string $sessionIndex,
        array $attributes,
    ): self {
        return new self(
            nameId: $nameId,
            nameIdFormat: $nameIdFormat,
            sessionIndex: $sessionIndex,
            email: self::firstAttribute($attributes, [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'urn:oid:0.9.2342.19200300.100.1.3',
                'email',
                'mail',
            ]),
            firstName: self::firstAttribute($attributes, [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
                'urn:oid:2.5.4.42',
                'givenName',
                'firstName',
            ]),
            lastName: self::firstAttribute($attributes, [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
                'urn:oid:2.5.4.4',
                'sn',
                'lastName',
            ]),
            displayName: self::firstAttribute($attributes, [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
                'urn:oid:2.16.840.1.113730.3.1.241',
                'displayName',
                'cn',
            ]),
            attributes: $attributes,
        );
    }

    /**
     * Convert to OAuthUser for unified handling across OAuth/OIDC/SAML.
     */
    public function toOAuthUser(): OAuthUser
    {
        $name = $this->displayName;
        if ($name === null && ($this->firstName || $this->lastName)) {
            $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        }

        return new OAuthUser(
            id: $this->nameId,
            email: $this->email,
            name: $name ?: null,
            avatar: null,
            provider: 'saml',
            raw: $this->attributes,
        );
    }

    /**
     * Find the first matching attribute value from a list of possible attribute names.
     *
     * @param array<string, string[]> $attributes
     * @param string[]                $keys
     */
    private static function firstAttribute(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($attributes[$key][0]) && $attributes[$key][0] !== '') {
                return $attributes[$key][0];
            }
        }

        return null;
    }
}
