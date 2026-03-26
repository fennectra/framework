<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Response;

class UiEmailTemplatesController
{
    use UiHelper;

    public function list(): void
    {
        try {
            $result = $this->paginate('email_templates');
            Response::json($result);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function show(int $id): void
    {
        try {
            $row = DB::raw('SELECT * FROM email_templates WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$row) {
                Response::json(['error' => 'Template not found'], 404);

                return;
            }

            Response::json($row);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function create(): void
    {
        $body = $this->body();
        $name = $body['name'] ?? '';
        $locale = $body['locale'] ?? 'en';
        $subject = $body['subject'] ?? '';
        $bodyContent = $body['body'] ?? '';

        if (!$name || !$subject || !$bodyContent) {
            Response::json(['error' => 'Name, subject, and body are required'], 422);

            return;
        }

        try {
            DB::raw(
                'INSERT INTO email_templates (name, locale, subject, body, created_at, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $locale, $subject, $bodyContent]
            );

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

        foreach (['name', 'locale', 'subject', 'body'] as $field) {
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
            DB::raw('UPDATE email_templates SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            DB::raw('DELETE FROM email_templates WHERE id = ?', [$id]);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function preview(int $id): void
    {
        try {
            $template = DB::raw('SELECT * FROM email_templates WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$template) {
                Response::json(['error' => 'Template not found'], 404);

                return;
            }

            $body = $this->body();
            $variables = $body['variables'] ?? [];

            $rendered = $template['body'];
            foreach ($variables as $key => $value) {
                $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
            }

            $renderedSubject = $template['subject'];
            foreach ($variables as $key => $value) {
                $renderedSubject = str_replace('{{' . $key . '}}', $value, $renderedSubject);
            }

            Response::json([
                'subject' => $renderedSubject,
                'body' => $rendered,
                'variables' => array_keys($variables),
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
