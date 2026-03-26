<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiAuditController
{
    use UiHelper;

    public function logs(): void
    {
        $action = $this->queryString('action');
        $table = $this->queryString('table');
        $userId = $this->queryString('user_id');

        $conditions = [];
        $params = [];

        if ($action) {
            $conditions[] = 'action = ?';
            $params[] = $action;
        }
        if ($table) {
            $conditions[] = 'auditable_type LIKE ?';
            $params[] = "%{$table}%";
        }
        if ($userId) {
            $conditions[] = 'user_id = ?';
            $params[] = $userId;
        }

        $where = $conditions ? implode(' AND ', $conditions) : '';

        try {
            $result = $this->paginate('audit_logs', $where, $params);

            foreach ($result['data'] as &$row) {
                $row['old_values'] = json_decode($row['old_values'] ?? '{}', true) ?? [];
                $row['new_values'] = json_decode($row['new_values'] ?? '{}', true) ?? [];
            }

            Response::json($result);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function stats(): void
    {
        try {
            $total = DB::raw('SELECT COUNT(*) as cnt FROM audit_logs')->fetchAll()[0]['cnt'] ?? 0;

            $actions = DB::raw(
                'SELECT action, COUNT(*) as cnt FROM audit_logs GROUP BY action ORDER BY cnt DESC'
            )->fetchAll();

            $tables = DB::raw(
                'SELECT auditable_type, COUNT(*) as cnt FROM audit_logs GROUP BY auditable_type ORDER BY cnt DESC'
            )->fetchAll();

            Response::json([
                'totalEntries' => (int) $total,
                'retentionDays' => (int) (Env::get('AUDIT_RETENTION_DAYS') ?: 365),
                'lastPurge' => null,
                'byAction' => $actions,
                'byTable' => $tables,
            ]);
        } catch (\Throwable) {
            Response::json([
                'totalEntries' => 0,
                'retentionDays' => 365,
                'lastPurge' => null,
                'byAction' => [],
                'byTable' => [],
            ]);
        }
    }

    public function purge(): void
    {
        $body = $this->body();
        $days = (int) ($body['days'] ?? 365);
        $dryRun = (bool) ($body['dryRun'] ?? true);

        if ($days < 30) {
            Response::json(['error' => 'Minimum retention is 30 days'], 422);

            return;
        }

        try {
            $driver = Env::get('DB_DRIVER') ?: 'pgsql';
            $dateExpr = match ($driver) {
                'mysql' => "DATE_SUB(CURRENT_TIMESTAMP, INTERVAL {$days} DAY)",
                'sqlite' => "datetime('now', '-{$days} days')",
                default => "CURRENT_TIMESTAMP - INTERVAL '{$days} days'",
            };

            $count = DB::raw(
                "SELECT COUNT(*) as cnt FROM audit_logs WHERE created_at < {$dateExpr}"
            )->fetchAll()[0]['cnt'] ?? 0;

            if (!$dryRun) {
                DB::raw("DELETE FROM audit_logs WHERE created_at < {$dateExpr}");
                SecurityLogger::track('audit.purged', ['days' => $days, 'deleted' => $count]);
            }

            Response::json(['deleted' => (int) $count, 'dryRun' => $dryRun]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): void
    {
        try {
            $row = DB::raw('SELECT * FROM audit_logs WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$row) {
                Response::json(['error' => 'Audit log not found'], 404);

                return;
            }

            $row['old_values'] = json_decode($row['old_values'] ?? '{}', true) ?? [];
            $row['new_values'] = json_decode($row['new_values'] ?? '{}', true) ?? [];

            Response::json($row);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
