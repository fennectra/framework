<?php

namespace Fennec\Core\OAuth\Providers;

use Fennec\Core\Env;
use Fennec\Core\OAuth\OAuthProvider;
use Fennec\Core\OAuth\OAuthToken;
use Fennec\Core\OAuth\OAuthUser;

/**
 * Fournisseur OAuth2 Google.
 *
 * Variables d'environnement :
 *   OAUTH_GOOGLE_CLIENT_ID
 *   OAUTH_GOOGLE_CLIENT_SECRET
 *   OAUTH_GOOGLE_REDIRECT
 */
class GoogleProvider extends OAuthProvider
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USER_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = Env::get('OAUTH_GOOGLE_CLIENT_ID');
        $this->clientSecret = Env::get('OAUTH_GOOGLE_CLIENT_SECRET');
        $this->redirectUri = Env::get('OAUTH_GOOGLE_REDIRECT');
    }

    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
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
            'grant_type' => 'authorization_code',
        ]);

        return new OAuthToken(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: $data['expires_in'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    public function getUserInfo(string $accessToken): OAuthUser
    {
        $data = $this->httpGet(self::USER_URL, [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        return new OAuthUser(
            id: (string) $data['id'],
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            avatar: $data['picture'] ?? null,
            provider: 'google',
            raw: $data,
        );
    }
}
