<?php

namespace Fennec\Core\OAuth;

use Fennec\Core\OAuth\Providers\GitHubProvider;
use Fennec\Core\OAuth\Providers\GoogleProvider;

/**
 * Factory pour les fournisseurs OAuth.
 *
 * Usage : (new OAuthManager())->driver('google')->getAuthorizationUrl($state);
 */
class OAuthManager
{
    /** @var array<string, OAuthProvider> */
    private array $providers = [];

    /**
     * Retourne une instance du fournisseur OAuth demande.
     */
    public function driver(string $name): OAuthProvider
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $provider = match ($name) {
            'google' => new GoogleProvider(),
            'github' => new GitHubProvider(),
            default => throw new \RuntimeException("Fournisseur OAuth inconnu : {$name}"),
        };

        $this->providers[$name] = $provider;

        return $provider;
    }
}
