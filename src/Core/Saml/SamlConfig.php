<?php

namespace Fennec\Core\Saml;

use Fennec\Core\Env;

/**
 * Configuration for SAML Service Provider and Identity Provider.
 *
 * Environment variables:
 *   SAML_SP_ENTITY_ID        — Service Provider entity ID (your app URL)
 *   SAML_SP_ACS_URL          — Assertion Consumer Service URL (POST callback)
 *   SAML_SP_SLO_URL          — Single Logout URL (optional)
 *   SAML_SP_PRIVATE_KEY      — SP private key PEM (for signing requests, optional)
 *   SAML_SP_CERTIFICATE      — SP certificate PEM (for IdP to encrypt assertions, optional)
 *   SAML_IDP_ENTITY_ID       — Identity Provider entity ID
 *   SAML_IDP_SSO_URL         — IdP Single Sign-On URL (HTTP-Redirect binding)
 *   SAML_IDP_SLO_URL         — IdP Single Logout URL (optional)
 *   SAML_IDP_CERTIFICATE     — IdP X.509 certificate PEM (for verifying signatures)
 *   SAML_WANT_SIGNED         — Whether assertions must be signed (default: true)
 */
class SamlConfig
{
    public function __construct(
        // Service Provider
        public readonly string $spEntityId,
        public readonly string $spAcsUrl,
        public readonly ?string $spSloUrl = null,
        public readonly ?string $spPrivateKey = null,
        public readonly ?string $spCertificate = null,
        // Identity Provider
        public readonly string $idpEntityId = '',
        public readonly string $idpSsoUrl = '',
        public readonly ?string $idpSloUrl = null,
        public readonly ?string $idpCertificate = null,
        // Options
        public readonly bool $wantAssertionsSigned = true,
    ) {
    }

    /**
     * Build config from environment variables.
     */
    public static function fromEnv(): self
    {
        $spEntityId = Env::get('SAML_SP_ENTITY_ID', '');
        $spAcsUrl = Env::get('SAML_SP_ACS_URL', '');

        if (empty($spEntityId)) {
            throw new SamlException('SAML_SP_ENTITY_ID is required');
        }
        if (empty($spAcsUrl)) {
            throw new SamlException('SAML_SP_ACS_URL is required');
        }

        return new self(
            spEntityId: $spEntityId,
            spAcsUrl: $spAcsUrl,
            spSloUrl: Env::get('SAML_SP_SLO_URL') ?: null,
            spPrivateKey: self::loadPem(Env::get('SAML_SP_PRIVATE_KEY') ?: null),
            spCertificate: self::loadPem(Env::get('SAML_SP_CERTIFICATE') ?: null),
            idpEntityId: Env::get('SAML_IDP_ENTITY_ID', ''),
            idpSsoUrl: Env::get('SAML_IDP_SSO_URL', ''),
            idpSloUrl: Env::get('SAML_IDP_SLO_URL') ?: null,
            idpCertificate: self::loadPem(Env::get('SAML_IDP_CERTIFICATE') ?: null),
            wantAssertionsSigned: Env::get('SAML_WANT_SIGNED', 'true') === 'true',
        );
    }

    /**
     * Build config with explicit values (useful for testing or programmatic setup).
     *
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            spEntityId: $params['sp_entity_id'] ?? '',
            spAcsUrl: $params['sp_acs_url'] ?? '',
            spSloUrl: $params['sp_slo_url'] ?? null,
            spPrivateKey: $params['sp_private_key'] ?? null,
            spCertificate: $params['sp_certificate'] ?? null,
            idpEntityId: $params['idp_entity_id'] ?? '',
            idpSsoUrl: $params['idp_sso_url'] ?? '',
            idpSloUrl: $params['idp_slo_url'] ?? null,
            idpCertificate: $params['idp_certificate'] ?? null,
            wantAssertionsSigned: $params['want_assertions_signed'] ?? true,
        );
    }

    /**
     * Load PEM content — supports both inline PEM and file paths.
     */
    private static function loadPem(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If it starts with -----BEGIN, it's inline PEM
        if (str_starts_with($value, '-----BEGIN')) {
            return $value;
        }

        // Otherwise treat as file path
        if (is_file($value) && is_readable($value)) {
            return file_get_contents($value) ?: null;
        }

        return null;
    }
}
