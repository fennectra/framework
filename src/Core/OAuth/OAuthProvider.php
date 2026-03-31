<?php

namespace Fennec\Core\OAuth;

/**
 * Classe de base abstraite pour les fournisseurs OAuth2.
 *
 * Fournit des helpers HTTP via file_get_contents + stream_context_create.
 */
abstract class OAuthProvider
{
    /**
     * URL d'autorisation avec state CSRF.
     */
    abstract public function getAuthorizationUrl(string $state): string;

    /**
     * Echange le code d'autorisation contre un token.
     */
    abstract public function getAccessToken(string $code): OAuthToken;

    /**
     * Recupere les informations utilisateur a partir du token.
     */
    abstract public function getUserInfo(string $accessToken): OAuthUser;

    /**
     * Requete HTTP GET.
     *
     * @param string               $url
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        $headerStr = $this->buildHeaders($headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerStr,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("OAuth HTTP GET failed: {$url}");
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Requete HTTP POST.
     *
     * @param string               $url
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function httpPost(string $url, array $data = [], array $headers = []): array
    {
        $body = http_build_query($data);
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded';
        $headerStr = $this->buildHeaders($headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerStr,
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("OAuth HTTP POST failed: {$url}");
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Requete HTTP POST avec body JSON.
     *
     * @param string               $url
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function httpPostJson(string $url, array $data = [], array $headers = []): array
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers['Content-Type'] = 'application/json';
        $headerStr = $this->buildHeaders($headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerStr,
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("OAuth HTTP POST (JSON) failed: {$url}");
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Raw HTTP GET returning the response body as string.
     *
     * @param string               $url
     * @param array<string, string> $headers
     */
    protected function httpGetRaw(string $url, array $headers = []): string
    {
        $headerStr = $this->buildHeaders($headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerStr,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("OAuth HTTP GET failed: {$url}");
        }

        return $response;
    }

    /**
     * Construit la chaine d'en-tetes HTTP.
     *
     * @param array<string, string> $headers
     */
    private function buildHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }

        return implode("\r\n", $lines);
    }
}
