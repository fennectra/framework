<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('audit_logs')]
class AuditLog extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    /**
     * Retourne les logs d'audit pour une entite donnee.
     */
    public static function forEntity(string $type, int $id): array
    {
        return static::where('auditable_type', '=', $type)
            ->where('auditable_id', '=', $id)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Retourne les logs d'audit pour un utilisateur donne.
     */
    public static function byUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = DB::raw(
            'SELECT * FROM audit_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['user_id' => $userId, 'limit' => $limit, 'offset' => $offset]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les statistiques par type d'action.
     */
    public static function stats(): array
    {
        $stmt = DB::raw(
            'SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action ORDER BY count DESC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les dernieres entrees d'audit.
     */
    public static function recentActivity(int $limit = 20): array
    {
        $stmt = DB::raw(
            'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :limit',
            ['limit' => $limit]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche avec filtres (action, auditable_type, user_id, date_from, date_to).
     */
    public static function search(array $filters, int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['auditable_type'])) {
            $where[] = 'auditable_type = :auditable_type';
            $params['auditable_type'] = $filters['auditable_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT * FROM audit_logs';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = DB::raw($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}