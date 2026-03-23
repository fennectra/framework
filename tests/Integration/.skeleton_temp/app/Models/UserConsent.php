<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\BelongsTo;

#[Table('user_consents')]
class UserConsent extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'user_id' => 'int',
        'consent_object_id' => 'int',
        'consent_status' => 'bool',
        'object_version' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function consentObject(): BelongsTo
    {
        return $this->belongsTo(ConsentObject::class, 'consent_object_id');
    }

    /**
     * Enregistre ou met a jour le consentement d'un utilisateur.
     */
    public static function recordConsent(
        int $userId,
        int $consentObjectId,
        bool $status,
        int $objectVersion,
        string $way = 'web'
    ): self {
        $existing = static::where('user_id', '=', $userId)
            ->where('consent_object_id', '=', $consentObjectId)
            ->first();

        if ($existing) {
            $existing->fill([
                'consent_status' => $status,
                'object_version' => $objectVersion,
                'consent_way' => $way,
            ])->save();

            return $existing;
        }

        return static::create([
            'user_id' => $userId,
            'consent_object_id' => $consentObjectId,
            'consent_status' => $status,
            'object_version' => $objectVersion,
            'consent_way' => $way,
        ]);
    }

    /**
     * Verifie si un utilisateur a accepte tous les documents requis (derniere version).
     */
    public static function hasAcceptedAll(int $userId): bool
    {
        $required = ConsentObject::allLatest();
        foreach ($required as $doc) {
            if (!$doc->getAttribute('is_required')) {
                continue;
            }
            $consent = static::where('user_id', '=', $userId)
                ->where('consent_object_id', '=', $doc->getAttribute('id'))
                ->first();
            if (!$consent || !$consent->getAttribute('consent_status')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Statistiques de consentement par document (DPO).
     */
    public static function statsByDocument(): array
    {
        $stmt = DB::raw(
            'SELECT co.id, co.object_name, co.key, co.object_version, co.is_required,
                    COUNT(uc.id) as total_responses,
                    COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) as accepted,
                    COUNT(CASE WHEN uc.consent_status = FALSE THEN 1 END) as refused
             FROM consent_objects co
             LEFT JOIN user_consents uc ON uc.consent_object_id = co.id
             GROUP BY co.id, co.object_name, co.key, co.object_version, co.is_required
             ORDER BY co.key, co.object_version DESC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Taux de conformite global (DPO).
     */
    public static function complianceRate(): array
    {
        $stmt = DB::raw(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE is_active = TRUE) as total_active_users,
                COUNT(DISTINCT compliant.user_id) as compliant_users
             FROM (
                SELECT uc.user_id
                FROM user_consents uc
                JOIN consent_objects co ON co.id = uc.consent_object_id
                JOIN users u ON u.id = uc.user_id AND u.is_active = TRUE
                WHERE co.is_required = TRUE
                  AND co.id IN (
                    SELECT co2.id FROM consent_objects co2
                    WHERE co2.object_version = (
                        SELECT MAX(co3.object_version) FROM consent_objects co3 WHERE co3.key = co2.key
                    )
                  )
                  AND uc.consent_status = TRUE
                GROUP BY uc.user_id
                HAVING COUNT(DISTINCT co.key) = (
                    SELECT COUNT(DISTINCT co4.key)
                    FROM consent_objects co4
                    WHERE co4.is_required = TRUE
                      AND co4.object_version = (
                          SELECT MAX(co5.object_version) FROM consent_objects co5 WHERE co5.key = co4.key
                      )
                )
             ) compliant'
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int) ($row['total_active_users'] ?? 0);
        $compliant = (int) ($row['compliant_users'] ?? 0);

        return [
            'total_active_users' => $total,
            'compliant_users' => $compliant,
            'non_compliant_users' => $total - $compliant,
            'compliance_rate' => $total > 0 ? round($compliant / $total * 100, 2) : 0,
        ];
    }

    /**
     * Utilisateurs non conformes (DPO).
     */
    public static function nonCompliantUsers(int $limit = 50, int $offset = 0): array
    {
        $stmt = DB::raw(
            'SELECT u.id, u.email, u.created_at,
                    COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) as accepted_count,
                    COUNT(CASE WHEN uc.consent_status = FALSE OR uc.id IS NULL THEN 1 END) as missing_count
             FROM users u
             LEFT JOIN user_consents uc ON uc.user_id = u.id
                 AND uc.consent_object_id IN (
                     SELECT co.id FROM consent_objects co
                     WHERE co.is_required = TRUE
                       AND co.object_version = (
                           SELECT MAX(co2.object_version) FROM consent_objects co2 WHERE co2.key = co.key
                       )
                 )
             WHERE u.is_active = TRUE
             GROUP BY u.id, u.email, u.created_at
             HAVING COUNT(CASE WHEN uc.consent_status = TRUE THEN 1 END) < (
                 SELECT COUNT(DISTINCT co3.key)
                 FROM consent_objects co3
                 WHERE co3.is_required = TRUE
                   AND co3.object_version = (
                       SELECT MAX(co4.object_version) FROM consent_objects co4 WHERE co4.key = co3.key
                   )
             )
             ORDER BY u.email
             LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Historique de consentement d'un utilisateur (droit d'acces RGPD).
     */
    public static function userHistory(int $userId): array
    {
        $stmt = DB::raw(
            'SELECT uc.id, co.object_name, co.key, uc.object_version,
                    uc.consent_status, uc.consent_way, uc.created_at, uc.updated_at
             FROM user_consents uc
             JOIN consent_objects co ON co.id = uc.consent_object_id
             WHERE uc.user_id = :user_id
             ORDER BY uc.updated_at DESC',
            ['user_id' => $userId]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export des consentements (droit a la portabilite RGPD).
     */
    public static function exportForUser(int $userId): array
    {
        return [
            'user_id' => $userId,
            'exported_at' => date('c'),
            'consents' => self::userHistory($userId),
        ];
    }

    /**
     * Retrait de tous les consentements (droit a l'oubli RGPD).
     */
    public static function withdrawAll(int $userId): int
    {
        $consents = static::where('user_id', '=', $userId)->get();
        $count = 0;
        foreach ($consents as $consent) {
            $consent->fill(['consent_status' => false, 'consent_way' => 'withdrawal'])->save();
            $count++;
        }

        return $count;
    }
}