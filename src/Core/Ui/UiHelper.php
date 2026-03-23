<?php

namespace Fennec\Core\Ui;

trait UiHelper
{
    protected function parseMemoryLimit(string $limit): int
    {
        $value = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    protected function parseLogLine(string $line): ?array
    {
        if (!preg_match('/^\[(.+?)\] \w+\.(\w+): (.+?) (\{.+\})/', $line, $m)) {
            return null;
        }

        $context = json_decode($m[4], true) ?? [];

        return [
            'timestamp' => $m[1],
            'level' => $m[2],
            'event' => $m[3],
            'context' => $context,
        ];
    }

    protected function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    protected function queryInt(string $key, int $default = 0): int
    {
        return (int) ($_GET[$key] ?? $default);
    }

    protected function queryString(string $key, string $default = ''): string
    {
        return $_GET[$key] ?? $default;
    }

    protected function paginate(string $table, string $where = '', array $params = [], int $perPage = 20): array
    {
        $page = max(1, $this->queryInt('page', 1));
        $offset = ($page - 1) * $perPage;
        $whereClause = $where ? "WHERE {$where}" : '';

        $total = \Fennec\Core\DB::raw(
            "SELECT COUNT(*) as cnt FROM {$table} {$whereClause}",
            $params
        )->fetchAll()[0]['cnt'] ?? 0;

        $rows = \Fennec\Core\DB::raw(
            "SELECT * FROM {$table} {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        return [
            'data' => $rows,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil((int) $total / $perPage),
        ];
    }
}
