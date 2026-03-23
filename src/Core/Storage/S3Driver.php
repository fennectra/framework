<?php

namespace Fennec\Core\Storage;

use Aws\S3\S3Client;
use Fennec\Core\Env;

class S3Driver implements StorageDriverInterface
{
    private S3Client $client;
    private string $bucket;
    private string $prefix;

    public function __construct(
        ?string $key = null,
        ?string $secret = null,
        ?string $region = null,
        ?string $bucket = null,
        ?string $endpoint = null,
        ?string $prefix = null,
    ) {
        $this->bucket = $bucket ?? Env::get('S3_BUCKET', '');
        $this->prefix = $prefix ?? Env::get('S3_PREFIX', '');

        $config = [
            'version' => 'latest',
            'region' => $region ?? Env::get('S3_REGION', 'eu-west-3'),
            'credentials' => [
                'key' => $key ?? Env::get('S3_KEY', ''),
                'secret' => $secret ?? Env::get('S3_SECRET', ''),
            ],
        ];

        $customEndpoint = $endpoint ?? Env::get('S3_ENDPOINT');
        if ($customEndpoint) {
            $config['endpoint'] = $customEndpoint;
            $config['use_path_style_endpoint'] = true;
        }

        $this->client = new S3Client($config);
    }

    public function put(string $path, string $contents): bool
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixedPath($path),
            'Body' => $contents,
        ]);

        return true;
    }

    public function get(string $path): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixedPath($path),
            ]);

            return (string) $result['Body'];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    public function exists(string $path): bool
    {
        return $this->client->doesObjectExistV2($this->bucket, $this->prefixedPath($path));
    }

    public function delete(string $path): bool
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->prefixedPath($path),
        ]);

        return true;
    }

    public function url(string $path): string
    {
        $customUrl = Env::get('S3_URL');
        if ($customUrl) {
            return rtrim($customUrl, '/') . '/' . $this->prefixedPath($path);
        }

        return $this->client->getObjectUrl($this->bucket, $this->prefixedPath($path));
    }

    public function copy(string $from, string $to): bool
    {
        $this->client->copyObject([
            'Bucket' => $this->bucket,
            'CopySource' => $this->bucket . '/' . $this->prefixedPath($from),
            'Key' => $this->prefixedPath($to),
        ]);

        return true;
    }

    public function move(string $from, string $to): bool
    {
        $this->copy($from, $to);
        $this->delete($from);

        return true;
    }

    public function size(string $path): ?int
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->prefixedPath($path),
            ]);

            return $result['ContentLength'] ?? null;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    public function files(string $directory = ''): array
    {
        $prefix = $this->prefixedPath($directory);
        if ($prefix && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $result = $this->client->listObjectsV2([
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
        ]);

        $files = [];
        foreach ($result['Contents'] ?? [] as $object) {
            $key = $object['Key'];
            if ($this->prefix) {
                $key = substr($key, strlen($this->prefix));
            }
            $files[] = ltrim($key, '/');
        }

        return $files;
    }

    /**
     * Genere une URL pre-signee pour upload/download direct.
     */
    public function temporaryUrl(string $path, int $expiration = 3600, string $method = 'GetObject'): string
    {
        $cmd = $this->client->getCommand($method, [
            'Bucket' => $this->bucket,
            'Key' => $this->prefixedPath($path),
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");

        return (string) $request->getUri();
    }

    public function absolutePath(string $path): ?string
    {
        return null; // S3 ne supporte pas l'acces fichier direct
    }

    private function prefixedPath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->prefix) {
            return rtrim($this->prefix, '/') . '/' . $path;
        }

        return $path;
    }
}
