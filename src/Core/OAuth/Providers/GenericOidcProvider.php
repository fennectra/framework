<?php

namespace Fennec\Core\OAuth\Providers;

use Fennec\Core\Env;
use Fennec\Core\OAuth\OidcProvider;

/**
 * Generic OpenID Connect provider.
 *
 * Works with any OIDC-compliant identity provider (Keycloak, Azure AD,
 * France Travail, Auth0, Okta, etc.) by specifying the issuer URL.
 *
 * Environment variables:
 *   OIDC_ISSUER          — OpenID Connect issuer URL
 *   OIDC_CLIENT_ID       — OAuth2 client ID
 *   OIDC_CLIENT_SECRET   — OAuth2 client secret
 *   OIDC_REDIRECT        — Redirect URI after authorization
 *   OIDC_SCOPES          — Additional scopes (comma-separated, default: email,profile)
 *   OIDC_PKCE            — Enable PKCE (true/false, default: false)
 */
class GenericOidcProvider extends OidcProvider
{
    private string $issuer;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    /** @var string[] */
    private array $scopes;
    private bool $pkce;

    public function __construct(
        ?string $issuer = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $redirectUri = null,
        ?array $scopes = null,
        ?bool $pkce = null,
    ) {
        $this->issuer = $issuer ?? Env::get('OIDC_ISSUER', '');
        $this->clientId = $clientId ?? Env::get('OIDC_CLIENT_ID', '');
        $this->clientSecret = $clientSecret ?? Env::get('OIDC_CLIENT_SECRET', '');
        $this->redirectUri = $redirectUri ?? Env::get('OIDC_REDIRECT', '');
        $this->pkce = $pkce ?? (Env::get('OIDC_PKCE', 'false') === 'true');

        if ($scopes !== null) {
            $this->scopes = $scopes;
        } else {
            $envScopes = Env::get('OIDC_SCOPES', 'email,profile');
            $this->scopes = array_map('trim', explode(',', $envScopes));
        }

        if (empty($this->issuer)) {
            throw new \RuntimeException('OIDC_ISSUER is required');
        }
        if (empty($this->clientId)) {
            throw new \RuntimeException('OIDC_CLIENT_ID is required');
        }
    }

    protected function getIssuer(): string
    {
        return $this->issuer;
    }

    protected function getClientId(): string
    {
        return $this->clientId;
    }

    protected function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    protected function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    protected function getScopes(): array
    {
        return $this->scopes;
    }

    protected function usePkce(): bool
    {
        return $this->pkce;
    }

    protected function getProviderName(): string
    {
        return 'oidc';
    }
}
