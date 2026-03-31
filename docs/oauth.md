# OAuth / OpenID Connect

> OAuth2 and OpenID Connect authentication with Google, GitHub, and any OIDC-compliant provider, via an extensible manager and immutable value objects.

## Vue d'ensemble

Le module OAuth fournit une abstraction pour l'authentification sociale (OAuth2) et l'authentification d'entreprise (OpenID Connect). Il supporte nativement Google, GitHub et tout fournisseur OIDC (France Travail, Keycloak, Azure AD, Auth0, Okta...).

Le module se decompose en 2 couches :
- **OAuth2** : flow standard en 3 etapes (authorize → token → userinfo) pour Google et GitHub
- **OIDC** : etend OAuth2 avec auto-discovery, validation du `id_token` via JWKS, et support PKCE

Les requetes HTTP sont faites via `file_get_contents` + `stream_context_create` (aucune dependance cURL).

## Diagramme

```mermaid
graph TD
    A[Client] -->|1. Redirect| B[OAuthManager]
    B -->|driver google| C[GoogleProvider]
    B -->|driver github| D[GitHubProvider]
    B -->|driver oidc| E[GenericOidcProvider]
    B -->|extend custom| F[Custom Provider]
    E -->|auto-discovery| G[/.well-known/openid-configuration]
    G -->|endpoints| H[Token + JWKS]
    H -->|id_token| I[OidcClaims]
    C -->|token| J[OAuthToken]
    D -->|token| J
    E -->|token| J
    J -->|userinfo| K[OAuthUser]
    I -->|toOAuthUser| K

    classDef chamois fill:#d4a574,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef blanc fill:#fefefe,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef beige fill:#f5e6d3,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef success fill:#e8dcc8,color:#3b2314,stroke:#b8895a,stroke-width:2px

    class A blanc
    class B chamois
    class C,D,E,F beige
    class G,H success
    class I,J,K chamois
```

## API publique

### OAuthManager (factory)

```php
$oauth = new OAuthManager();

// Providers integres
$google = $oauth->driver('google');
$github = $oauth->driver('github');
$oidc   = $oauth->driver('oidc');     // GenericOidcProvider

// Enregistrer un provider custom
$oauth->extend('france_travail', fn () => new GenericOidcProvider(
    issuer: 'https://authentification-candidat.francetravail.fr/connexion/oauth2',
    clientId: Env::get('FT_CLIENT_ID'),
    clientSecret: Env::get('FT_CLIENT_SECRET'),
    redirectUri: Env::get('FT_REDIRECT'),
    pkce: true,
));
$ft = $oauth->driver('france_travail');
```

Providers disponibles : `google`, `github`, `oidc`. Tout autre nom non enregistre via `extend()` leve une `RuntimeException`.

### OAuthProvider (classe abstraite)

Chaque provider OAuth2 implemente 3 methodes :

```php
// 1. URL d'autorisation (avec state CSRF)
$url = $provider->getAuthorizationUrl($state);

// 2. Echange du code contre un token
$token = $provider->getAccessToken($code);

// 3. Informations utilisateur
$user = $provider->getUserInfo($token->accessToken);
```

Methodes HTTP protegees pour les providers custom :

```php
$data = $this->httpGet($url, ['Authorization' => 'Bearer xxx']);
$data = $this->httpPost($url, ['key' => 'value'], ['Accept' => 'application/json']);
$data = $this->httpPostJson($url, ['key' => 'value']);  // Content-Type: application/json
$raw  = $this->httpGetRaw($url);                        // reponse brute (string)
```

### OidcProvider (classe abstraite — etend OAuthProvider)

Ajoute les fonctionnalites OpenID Connect :

```php
// Auto-discovery
$discovery = $provider->discover();
// => ['authorization_endpoint' => '...', 'token_endpoint' => '...', 'jwks_uri' => '...', ...]

// Authorization URL avec nonce et PKCE optionnel
$url = $provider->getAuthorizationUrl($state);
// Le resultat contient _nonce et _code_verifier (a stocker en session)

// Echange du code (avec code_verifier si PKCE)
$token = $provider->getAccessToken($code, $codeVerifier);

// Validation du id_token (JWT signe par l'IdP)
$claims = $provider->validateIdToken($token->idToken, $expectedNonce);

// Generer PKCE verifier/challenge
$verifier  = $provider->generateCodeVerifier();
$challenge = $provider->generateCodeChallenge($verifier);
```

Validations effectuees sur le `id_token` :
- Signature RSA via JWKS (RS256, RS384, RS512)
- Issuer — doit correspondre au provider
- Audience — doit contenir le `client_id`
- Expiration — avec 5 secondes de tolerance
- Nonce — protection anti-replay (si fourni)

### GenericOidcProvider

Provider OIDC configurable pour tout IdP compatible :

```php
// Via variables d'environnement
$provider = new GenericOidcProvider();

// Via constructeur (pour tests ou config dynamique)
$provider = new GenericOidcProvider(
    issuer: 'https://idp.example.com',
    clientId: 'my-client',
    clientSecret: 'my-secret',
    redirectUri: 'https://app.com/callback',
    scopes: ['email', 'profile'],
    pkce: true,
);
```

### OAuthToken (value object)

```php
$token->accessToken;   // string — access token
$token->refreshToken;  // ?string — refresh token
$token->expiresIn;     // ?int — duree de validite en secondes
$token->tokenType;     // string — type (default: 'Bearer')
$token->idToken;       // ?string — id_token JWT (OIDC uniquement)
```

### OAuthUser (value object)

```php
$user->id;        // string — identifiant unique chez le provider
$user->email;     // ?string — email
$user->name;      // ?string — nom complet
$user->avatar;    // ?string — URL de l'avatar
$user->provider;  // string — 'google', 'github', 'oidc', etc.
$user->raw;       // array — donnees brutes de l'API
```

### OidcClaims (value object)

```php
$claims->sub;            // string — sujet (identifiant unique)
$claims->email;          // ?string
$claims->emailVerified;  // ?bool
$claims->name;           // ?string
$claims->givenName;      // ?string
$claims->familyName;     // ?string
$claims->picture;        // ?string
$claims->locale;         // ?string
$claims->nonce;          // ?string
$claims->issuer;         // ?string
$claims->audience;       // ?string
$claims->issuedAt;       // ?int
$claims->expiresAt;      // ?int
$claims->raw;            // array — claims bruts

// Construire depuis un tableau
$claims = OidcClaims::fromArray($decodedPayload);

// Convertir en OAuthUser pour un traitement unifie
$user = $claims->toOAuthUser('france_travail');
```

## Configuration

### Google

| Variable | Description |
|---|---|
| `OAUTH_GOOGLE_CLIENT_ID` | Client ID de l'application Google |
| `OAUTH_GOOGLE_CLIENT_SECRET` | Client Secret |
| `OAUTH_GOOGLE_REDIRECT` | URL de callback |

Scopes : `openid email profile` — Options : `access_type=offline`, `prompt=consent`

### GitHub

| Variable | Description |
|---|---|
| `OAUTH_GITHUB_CLIENT_ID` | Client ID de l'application GitHub |
| `OAUTH_GITHUB_CLIENT_SECRET` | Client Secret |
| `OAUTH_GITHUB_REDIRECT` | URL de callback |

Scopes : `user:email read:user` — Fallback automatique sur `/user/emails` si le profil ne contient pas d'email.

### OIDC (generique)

| Variable | Default | Description |
|---|---|---|
| `OIDC_ISSUER` | — | URL de l'issuer OIDC (obligatoire) |
| `OIDC_CLIENT_ID` | — | Client ID (obligatoire) |
| `OIDC_CLIENT_SECRET` | — | Client Secret |
| `OIDC_REDIRECT` | — | URL de callback |
| `OIDC_SCOPES` | `email,profile` | Scopes supplementaires (separes par virgule) |
| `OIDC_PKCE` | `false` | Activer PKCE (`true`/`false`) |

## Integration avec d'autres modules

- **Auth/JWT** : apres authentification OAuth/OIDC, generer un JWT via `JwtService`
- **User Model** : creer ou retrouver l'utilisateur local depuis `OAuthUser`
- **SecurityLogger** : tracer les connexions OAuth dans les logs de securite
- **SAML** : `SamlUser::toOAuthUser()` convertit vers `OAuthUser` pour un traitement unifie

## Exemple complet — OIDC

```php
use Fennec\Core\OAuth\OAuthManager;

class OidcController
{
    private OAuthManager $oauth;

    public function __construct()
    {
        $this->oauth = new OAuthManager();
    }

    public function redirect(): array
    {
        $state = bin2hex(random_bytes(16));
        $url = $this->oauth->driver('oidc')->getAuthorizationUrl($state);

        // Extraire nonce et code_verifier du query string
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $params['_nonce'] ?? null;
        $_SESSION['oidc_verifier'] = $params['_code_verifier'] ?? null;

        // Nettoyer les parametres internes de l'URL
        $cleanUrl = preg_replace('/[&?]_(?:nonce|code_verifier)=[^&]*/', '', $url);

        return ['redirect' => $cleanUrl];
    }

    public function callback(string $code, string $state): array
    {
        if ($state !== ($_SESSION['oidc_state'] ?? '')) {
            throw new \RuntimeException('CSRF state mismatch');
        }

        $provider = $this->oauth->driver('oidc');
        $token = $provider->getAccessToken($code, $_SESSION['oidc_verifier'] ?? null);

        // Valider le id_token
        $claims = $provider->validateIdToken(
            $token->idToken,
            $_SESSION['oidc_nonce'] ?? null
        );

        $user = $claims->toOAuthUser('oidc');
        // => OAuthUser(id: 'sub-123', email: 'user@example.com', ...)

        return ['user' => $user];
    }
}
```

## Exemple — Provider custom via extend()

```php
$oauth = new OAuthManager();
$oauth->extend('france_travail', fn () => new GenericOidcProvider(
    issuer: 'https://authentification-candidat.francetravail.fr/connexion/oauth2',
    clientId: Env::get('FT_CLIENT_ID'),
    clientSecret: Env::get('FT_CLIENT_SECRET'),
    redirectUri: Env::get('FT_REDIRECT'),
    scopes: ['api_peconnect-individuv1', 'email', 'profile'],
    pkce: true,
));

$ft = $oauth->driver('france_travail');
$url = $ft->getAuthorizationUrl($state);
```

## Architecture interne

```
OAuthProvider (abstract)
├── httpGet(), httpPost(), httpPostJson(), httpGetRaw()
├── GoogleProvider
├── GitHubProvider
└── OidcProvider (abstract — etend OAuthProvider)
    ├── discover()           → .well-known/openid-configuration
    ├── fetchJwks()          → cles publiques du IdP
    ├── validateIdToken()    → verifie signature + claims
    ├── generateCodeVerifier/Challenge()  → PKCE S256
    ├── jwkToPublicKey()     → RSA JWK → OpenSSL PEM
    └── GenericOidcProvider  → configurable via env ou constructeur

OAuthManager (factory)
├── driver('google' | 'github' | 'oidc')
├── extend('name', fn () => ...)
└── cache singleton par provider

Value Objects (readonly)
├── OAuthToken  {accessToken, refreshToken, expiresIn, tokenType, idToken}
├── OAuthUser   {id, email, name, avatar, provider, raw}
└── OidcClaims  {sub, email, name, nonce, issuer, audience, ...}
```

## Fichiers du module

| Fichier | Role | Derniere modif |
|---|---|---|
| `src/Core/OAuth/OAuthManager.php` | Factory des providers (cache singleton) + `extend()` | 2026-03-31 |
| `src/Core/OAuth/OAuthProvider.php` | Classe abstraite avec helpers HTTP | 2026-03-31 |
| `src/Core/OAuth/OidcProvider.php` | Classe abstraite OIDC (discovery, JWKS, id_token, PKCE) | 2026-03-31 |
| `src/Core/OAuth/OAuthToken.php` | Value object token (readonly) + `idToken` | 2026-03-31 |
| `src/Core/OAuth/OAuthUser.php` | Value object utilisateur (readonly) | 2026-03-21 |
| `src/Core/OAuth/OidcClaims.php` | Value object claims OIDC (readonly) | 2026-03-31 |
| `src/Core/OAuth/Providers/GoogleProvider.php` | Provider Google (OpenID Connect) | 2026-03-21 |
| `src/Core/OAuth/Providers/GitHubProvider.php` | Provider GitHub (+ fallback email) | 2026-03-21 |
| `src/Core/OAuth/Providers/GenericOidcProvider.php` | Provider OIDC generique | 2026-03-31 |
| `tests/Unit/OidcProviderTest.php` | 17 tests (claims, PKCE, id_token, signature) | 2026-03-31 |
