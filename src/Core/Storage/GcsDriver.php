<?php

namespace Fennec\Core\Storage;

use Fennec\Core\Env;
use Google\Cloud\Storage\StorageClient;

class GcsDriver implements StorageDriverInterface
{
    private StorageClient $client;
    private string $bucket;
    private string $prefix;
    private string $publicUrl;

    public function __construct(
        ?string $bucket = null,
        ?string $prefix = null,
        ?string $keyFilePath = null,
        ?string $projectId = null,
    ) {
        $this->bucket = $bucket ?? Env::get('GCS_BUCKET', '');
        $this->prefix = $prefix ?? Env::get('GCS_PREFIX', '');

        $config = [];

        // Project ID (optionnel si Workload Identity)
        $project = $projectId ?? Env::get('GCS_PROJECT');
        if ($project) {
            $config['projectId'] = $project;
        }

        // Authentification : keyFile ou Workload Identity automatique
        $keyFile = $keyFilePath ?? Env::get('GCS_KEY_FILE');
        if ($keyFile) {
            $config['keyFilePath'] = $keyFile;
        }

        $this->client = new StorageClient($config);

        // URL publique : CDN custom ou URL GCS par defaut
        $this->publicUrl = Env::get('GCS_URL', 'https://storage.googleapis.com/' . $this->bucket);
    }

    public function put(string $path, string $contents): bool
    {
        $this->getBucket()->upload($contents, [
            'name' => $this->prefixedPath($path),
        ]);

        return true;
    }

    public function get(string $path): ?string
    {
        try {
            $object = $this->getBucket()->object($this->prefixedPath($path));

            return $object->downloadAsString();
        } catch (\Google\Cloud\Core\Exception\NotFoundException) {
            return null;
        }
    }

    public function exists(string $path): bool
    {
        return $this->getBucket()->object($this->prefixedPath($path))->exists();
    }

    public function delete(string $path): bool
    {
        try {
            $this->getBucket()->object($this->prefixedPath($path))->delete();

            return true;
        } catch (\Google\Cloud\Core\Exception\NotFoundException) {
            return false;
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->publicUrl, '/') . '/' . $this->prefixedPath($path);
    }

    public function copy(string $from, string $to): bool
    {
        $this->getBucket()->object($this->prefixedPath($from))->copy(
            $this->getBucket(),
            ['name' => $this->prefixedPath($to)]
        );

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
            $info = $this->getBucket()->object($this->prefixedPath($path))->info();

            return (int) ($info['size'] ?? 0);
        } catch (\Google\Cloud\Core\Exception\NotFoundException) {
            return null;
        }
    }

    public function files(string $directory = ''): array
    {
        $prefix = $this->prefixedPath($directory);
        if ($prefix && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $objects = $this->getBucket()->objects(['prefix' => $prefix]);
        $files = [];

        foreach ($objects as $object) {
            $key = $object->name();
            if ($this->prefix) {
                $key = substr($key, strlen(rtrim($this->prefix, '/') . '/'));
            }
            $files[] = ltrim($key, '/');
        }

        return $files;
    }

    public function absolutePath(string $path): ?string
    {
        return null; // GCS ne supporte pas l'acces fichier direct
    }

    /**
     * Genere une URL signee temporaire pour upload/download direct.
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        $object = $this->getBucket()->object($this->prefixedPath($path));

        $url = $object->signedUrl(new \DateTime("+{$expiration} seconds"));

        return $url;
    }

    private function getBucket(): \Google\Cloud\Storage\Bucket
    {
        return $this->client->bucket($this->bucket);
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
