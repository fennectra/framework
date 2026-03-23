<?php

namespace Fennec\Core\Audit;

use Fennec\Attributes\Auditable;
use Fennec\Core\DB;
use Fennec\Core\Event;

/**
 * Trait pour les Models utilisant #[Auditable].
 *
 * Intercepte save() et delete() pour enregistrer les changements
 * dans la table audit_logs.
 *
 * Usage :
 *   #[Table('users'), Auditable(except: ['password'])]
 *   class User extends Model {
 *       use HasAuditTrail;
 *   }
 */
trait HasAuditTrail
{
    /** @var array<string, array{only: string[], except: string[]}> */
    private static array $auditConfigCache = [];

    public function save(): static
    {
        $isInsert = !$this->exists;

        // Capturer les dirty AVANT le save (car parent::save() sync $original)
        $dirty = $isInsert ? [] : $this->getDirty();
        $oldValues = [];
        foreach (array_keys($dirty) as $key) {
            $oldValues[$key] = $this->original[$key] ?? null;
        }

        // Deleguer au Model parent
        $result = parent::save();

        // Logger l'audit
        $action = $isInsert ? 'created' : 'updated';

        if ($isInsert) {
            $newValues = $this->filterAuditFields($this->toArray());
            $this->writeAuditLog($action, [], $newValues);
        } elseif (!empty($dirty)) {
            $filteredOld = $this->filterAuditFields($oldValues);
            $filteredNew = $this->filterAuditFields($dirty);

            if (!empty($filteredNew)) {
                $this->writeAuditLog($action, $filteredOld, $filteredNew);
            }
        }

        return $result;
    }

    public function delete(): bool
    {
        $snapshot = $this->filterAuditFields($this->toArray());

        $result = parent::delete();

        if ($result) {
            $this->writeAuditLog('deleted', $snapshot, []);
        }

        return $result;
    }

    /**
     * Filtre les champs selon la config #[Auditable].
     */
    private function filterAuditFields(array $data): array
    {
        $config = self::getAuditConfig();

        if (!empty($config['only'])) {
            $data = array_intersect_key($data, array_flip($config['only']));
        }

        if (!empty($config['except'])) {
            $data = array_diff_key($data, array_flip($config['except']));
        }

        return $data;
    }

    /**
     * Lit et cache la config de l'attribut #[Auditable].
     *
     * @return array{only: string[], except: string[]}
     */
    private static function getAuditConfig(): array
    {
        $class = static::class;

        if (isset(self::$auditConfigCache[$class])) {
            return self::$auditConfigCache[$class];
        }

        $ref = new \ReflectionClass($class);
        $attrs = $ref->getAttributes(Auditable::class);

        if (empty($attrs)) {
            self::$auditConfigCache[$class] = ['only' => [], 'except' => []];

            return self::$auditConfigCache[$class];
        }

        $instance = $attrs[0]->newInstance();
        self::$auditConfigCache[$class] = [
            'only' => $instance->only,
            'except' => $instance->except,
        ];

        return self::$auditConfigCache[$class];
    }

    private function writeAuditLog(string $action, array $oldValues, array $newValues): void
    {
        $userId = $_REQUEST['__auth_user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $requestId = $_SERVER['X_REQUEST_ID'] ?? null;

        try {
            DB::raw(
                'INSERT INTO audit_logs (auditable_type, auditable_id, action, old_values, new_values, user_id, ip_address, request_id, created_at) VALUES (:type, :id, :action, :old, :new, :user_id, :ip, :request_id, :created_at)',
                [
                    'type' => static::class,
                    'id' => $this->getKey(),
                    'action' => $action,
                    'old' => json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                    'new' => json_encode($newValues, JSON_UNESCAPED_UNICODE),
                    'user_id' => $userId,
                    'ip' => $ip,
                    'request_id' => $requestId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Ne jamais faire echouer une requete a cause de l'audit
        }

        Event::dispatch(static::class . '.audited', [
            'action' => $action,
            'model' => $this,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
