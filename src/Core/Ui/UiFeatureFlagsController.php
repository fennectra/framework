<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Feature\FeatureFlag;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiFeatureFlagsController
{
    use UiHelper;

    public function list(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM feature_flags ORDER BY key ASC')->fetchAll();

            foreach ($rows as &$row) {
                $row['rules'] = json_decode($row['rules'] ?? 'null', true);
                $row['enabled'] = (bool) $row['enabled'];
            }

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function create(): void
    {
        $body = $this->body();
        $key = $body['key'] ?? '';
        $enabled = (bool) ($body['enabled'] ?? false);
        $rules = $body['rules'] ?? null;
        $description = $body['description'] ?? '';

        if (!$key) {
            Response::json(['error' => 'Key is required'], 422);

            return;
        }

        try {
            FeatureFlag::define($key, $enabled, $rules);

            if ($description) {
                DB::raw('UPDATE feature_flags SET description = ? WHERE key = ?', [$description, $key]);
            }

            SecurityLogger::track('feature_flag.created', ['key' => $key, 'by' => 'admin_ui']);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(int $id): void
    {
        $body = $this->body();
        $fields = [];
        $params = [];

        if (isset($body['key'])) {
            $fields[] = 'key = ?';
            $params[] = $body['key'];
        }
        if (isset($body['enabled'])) {
            $fields[] = 'enabled = ?';
            $params[] = $body['enabled'] ? 1 : 0;
        }
        if (array_key_exists('rules', $body)) {
            $fields[] = 'rules = ?';
            $params[] = $body['rules'] ? json_encode($body['rules']) : null;
        }
        if (isset($body['description'])) {
            $fields[] = 'description = ?';
            $params[] = $body['description'];
        }

        if (!$fields) {
            Response::json(['error' => 'No fields to update'], 422);

            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        try {
            DB::raw('UPDATE feature_flags SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
            SecurityLogger::track('feature_flag.updated', ['id' => $id, 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            $flag = DB::raw('SELECT key FROM feature_flags WHERE id = ?', [$id])->fetchAll()[0] ?? null;
            DB::raw('DELETE FROM feature_flags WHERE id = ?', [$id]);
            SecurityLogger::track('feature_flag.deleted', ['id' => $id, 'key' => $flag['key'] ?? '', 'by' => 'admin_ui']);

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggle(int $id): void
    {
        try {
            $flag = DB::raw('SELECT * FROM feature_flags WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$flag) {
                Response::json(['error' => 'Feature flag not found'], 404);

                return;
            }

            $newState = !((bool) $flag['enabled']);
            DB::raw('UPDATE feature_flags SET enabled = ?, updated_at = NOW() WHERE id = ?', [$newState ? 1 : 0, $id]);

            SecurityLogger::track('feature_flag.toggled', [
                'key' => $flag['key'],
                'enabled' => $newState,
                'by' => 'admin_ui',
            ]);

            Response::json(['success' => true, 'enabled' => $newState]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
