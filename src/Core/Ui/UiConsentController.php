<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Response;

class UiConsentController
{
    use UiHelper;

    public function documents(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM consent_objects ORDER BY key ASC, version DESC')->fetchAll();
            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function createDocument(): void
    {
        $body = $this->body();
        $key = $body['key'] ?? '';
        $title = $body['title'] ?? '';
        $bodyContent = $body['body'] ?? '';
        $locale = $body['locale'] ?? 'en';

        if (!$key || !$title || !$bodyContent) {
            Response::json(['error' => 'Key, title, and body are required'], 422);

            return;
        }

        try {
            // Auto-increment version
            $lastVersion = DB::raw(
                'SELECT MAX(version) as v FROM consent_objects WHERE key = ?',
                [$key]
            )->fetchAll()[0]['v'] ?? 0;

            DB::raw(
                'INSERT INTO consent_objects (key, title, body, locale, version, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$key, $title, $bodyContent, $locale, (int) $lastVersion + 1]
            );

            Response::json(['success' => true, 'version' => (int) $lastVersion + 1]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateDocument(int $id): void
    {
        $body = $this->body();
        $fields = [];
        $params = [];

        foreach (['title', 'body', 'locale'] as $field) {
            if (isset($body[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $body[$field];
            }
        }

        if (!$fields) {
            Response::json(['error' => 'No fields to update'], 422);

            return;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        try {
            DB::raw('UPDATE consent_objects SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function stats(): void
    {
        try {
            $documents = (int) (DB::raw('SELECT COUNT(DISTINCT key) as cnt FROM consent_objects')->fetchAll()[0]['cnt'] ?? 0);
            $consents = (int) (DB::raw('SELECT COUNT(*) as cnt FROM user_consents WHERE accepted = true AND withdrawn = false')->fetchAll()[0]['cnt'] ?? 0);
            $withdrawn = (int) (DB::raw('SELECT COUNT(*) as cnt FROM user_consents WHERE withdrawn = true')->fetchAll()[0]['cnt'] ?? 0);
            $pending = (int) (DB::raw(
                'SELECT COUNT(DISTINCT u.id) as cnt FROM users u
                 WHERE u.id NOT IN (SELECT uc.user_id FROM user_consents uc WHERE uc.accepted = true AND uc.withdrawn = false)'
            )->fetchAll()[0]['cnt'] ?? 0);

            Response::json([
                'documents' => $documents,
                'accepted' => $consents,
                'withdrawn' => $withdrawn,
                'pendingUsers' => $pending,
            ]);
        } catch (\Throwable) {
            Response::json(['documents' => 0, 'accepted' => 0, 'withdrawn' => 0, 'pendingUsers' => 0]);
        }
    }

    public function userConsents(int $userId): void
    {
        try {
            $rows = DB::raw(
                'SELECT uc.*, co.key, co.title, co.version
                 FROM user_consents uc
                 JOIN consent_objects co ON uc.consent_object_id = co.id
                 WHERE uc.user_id = ?
                 ORDER BY uc.id DESC',
                [$userId]
            )->fetchAll();

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }
}
