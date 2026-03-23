<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Env;
use Fennec\Core\Response;
use Fennec\Core\Storage;

class UiStorageController
{
    use UiHelper;

    public function files(): void
    {
        $directory = $this->queryString('directory', '');

        try {
            $storage = Storage::getInstance();

            if (!$storage) {
                Response::json(['error' => 'Storage not initialized'], 503);

                return;
            }

            $files = Storage::files($directory);
            $result = [];

            foreach ($files as $file) {
                $size = Storage::size($file);
                $result[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $size,
                    'url' => Storage::url($file),
                    'extension' => pathinfo($file, PATHINFO_EXTENSION),
                ];
            }

            Response::json([
                'directory' => $directory,
                'files' => $result,
                'driver' => Env::get('STORAGE_DRIVER') ?: 'local',
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function upload(): void
    {
        if (empty($_FILES['file'])) {
            Response::json(['error' => 'No file uploaded'], 422);

            return;
        }

        $file = $_FILES['file'];
        $directory = $_POST['directory'] ?? '';
        $path = ($directory ? rtrim($directory, '/') . '/' : '') . $file['name'];

        try {
            $contents = file_get_contents($file['tmp_name']);
            Storage::put($path, $contents);

            Response::json([
                'success' => true,
                'path' => $path,
                'url' => Storage::url($path),
                'size' => $file['size'],
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $path = $body['path'] ?? '';

        if (!$path) {
            Response::json(['error' => 'Path is required'], 422);

            return;
        }

        try {
            Storage::delete($path);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function info(): void
    {
        $driver = Env::get('STORAGE_DRIVER') ?: 'local';

        $info = [
            'driver' => $driver,
        ];

        if ($driver === 'local') {
            $storagePath = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/' . (Env::get('STORAGE_PATH') ?: 'storage');
            if (is_dir($storagePath)) {
                $info['path'] = $storagePath;
                $info['writable'] = is_writable($storagePath);
                $info['freeSpace'] = disk_free_space($storagePath) ?: 0;
            }
        } elseif ($driver === 's3') {
            $info['bucket'] = Env::get('AWS_BUCKET') ?: '';
            $info['region'] = Env::get('AWS_REGION') ?: '';
        } elseif ($driver === 'gcs') {
            $info['bucket'] = Env::get('GCS_BUCKET') ?: '';
            $info['project'] = Env::get('GCS_PROJECT') ?: '';
        }

        Response::json($info);
    }
}
