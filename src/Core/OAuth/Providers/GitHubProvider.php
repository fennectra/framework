<?php

namespace Fennec\Core\OAuth\Providers;

use Fennec\Core\Env;
use Fennec\Core\OAuth\OAuthProvider;
use Fennec\Core\OAuth\OAuthToken;
use Fennec\Core\OAuth\OAuthUser;

/**
 * Fournisseur OAuth2 GitHub.
 *
 * Variables d'environnement :
 *   OAUTH_GITHUB_CLIENT_ID
 *   OAUTH_GITHUB_CLIENT_SECRET
 *   OAUTH_GITHUB_REDIRECT
 */
class GitHubProvider extends OAuthProvider
{
    private const AUTH_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_URL = 'https://api.github.com/user';
    private const EMAIL_URL = 'https://api.github.com/user/emails';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = Env::get('OAUTH_GITHUB_CLIENT_ID');
        $this->clientSecret = Env::get('OAUTH_GITHUB_CLIENT_SECRET');
        $this->redirectUri = Env::get('OAUTH_GITHUB_REDIRECT');
    }

    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'user:email read:user',
            'state' => $state,
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    public function getAccessToken(string $code): OAuthToken
    {
        $data = $this->httpPost(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ], [
            'Accept' => 'application/json',
        ]);

        return new OAuthToken(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: $data['expires_in'] ?? null,
            tokenType: $data['token_type'] ?? 'bearer',
        );
    }

    public function getUserInfo(string $accessToken): OAuthUser
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'User-Agent' => 'FennecPHP',
            'Accept' => 'application/json',
        ];

        $data = $this->httpGet(self::USER_URL, $headers);

        // GitHub peut ne pas inclure l'email dans le profil principal
        $email = $data['email'] ?? null;
        if ($email === null) {
            $email = $this->fetchPrimaryEmail($accessToken);
        }

        return new OAuthUser(
            id: (string) $data['id'],
            email: $email,
            name: $data['name'] ?? $data['login'] ?? null,
            avatar: $data['avatar_url'] ?? null,
            provider: 'github',
            raw: $data,
        );
    }

    /**
     * Recupere l'email principal depuis l'endpoint /user/emails.
     */
    private function fetchPrimaryEmail(string $accessToken): ?string
    {
        try {
            $emails = $this->httpGet(self::EMAIL_URL, [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'FennecPHP',
                'Accept' => 'application/json',
            ]);

            foreach ($emails as $entry) {
                if (!empty($entry['primary']) && !empty($entry['verified'])) {
                    return $entry['email'];
                }
            }

            $first = $emails[0] ?? null;

            return $first['email'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
