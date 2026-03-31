<?php

namespace Fennec\Core\OAuth;

use Fennec\Core\OAuth\Providers\GenericOidcProvider;
use Fennec\Core\OAuth\Providers\GitHubProvider;
use Fennec\Core\OAuth\Providers\GoogleProvider;

/**
 * Factory pour les fournisseurs OAuth / OIDC.
 *
 * Usage:
 *   (new OAuthManager())->driver('google')->getAuthorizationUrl($state);
 *   (new OAuthManager())->driver('oidc')->getAuthorizationUrl($state);
 */
class OAuthManager
{
    /** @var array<string, OAuthProvider> */
    private array $providers = [];

    /** @var array<string, \Closure> Custom provider factories */
    private array $customDrivers = [];

    /**
     * Retourne une instance du fournisseur OAuth/OIDC demande.
     */
    public function driver(string $name): OAuthProvider
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $provider = match (true) {
            isset($this->customDrivers[$name]) => ($this->customDrivers[$name])(),
            $name === 'google' => new GoogleProvider(),
            $name === 'github' => new GitHubProvider(),
            $name === 'oidc' => new GenericOidcProvider(),
            default => throw new \RuntimeException("Fournisseur OAuth inconnu : {$name}"),
        };

        $this->providers[$name] = $provider;

        return $provider;
    }

    /**
     * Register a custom provider factory.
     *
     * Usage:
     *   $manager->extend('france_travail', fn () => new GenericOidcProvider(
     *       issuer: 'https://authentification-candidat.francetravail.fr',
     *       clientId: Env::get('FT_CLIENT_ID'),
     *       ...
     *   ));
     *
     * @param string   $name    Provider name
     * @param \Closure $factory Factory that returns an OAuthProvider
     */
    public function extend(string $name, \Closure $factory): void
    {
        $this->customDrivers[$name] = $factory;
    }
}
