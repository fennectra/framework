<?php

namespace Fennec\Core\Nf525;

use Fennec\Attributes\Nf525;
use Fennec\Core\DB;
use Fennec\Core\Event;
use Fennec\Core\Security\SecurityLogger;

/**
 * Trait NF525 pour les Models de facturation.
 *
 * Gere l'inalterabilite, la numerotation sequentielle et le chainage SHA-256.
 *
 * Usage :
 *   #[Table('invoices'), Nf525(prefix: 'FA')]
 *   class Invoice extends Model {
 *       use HasNf525;
 *   }
 */
trait HasNf525
{
    /** @var array<string, array{prefix: string, sequenceColumn: string, hashColumn: string, prevHashColumn: string, excludeFromHash: string[]}> */
    private static array $nf525ConfigCache = [];

    /**
     * Override save() — interdit la modification, gere le chainage hash sur insert.
     */
    public function save(): static
    {
        if ($this->exists) {
            throw new \RuntimeException('NF525 — modification interdite. Utilisez createCredit() pour corriger.');
        }

        $config = self::getNf525Config();

        // Generer le numero sequentiel
        $sequence = $this->generateSequenceNumber($config);
        $this->attributes[$config['sequenceColumn']] = $sequence;

        // Recuperer le hash precedent
        $previousHash = $this->getLastHash($config);
        $this->attributes[$config['prevHashColumn']] = $previousHash;

        // Appeler parent::save() pour persister (INSERT)
        $result = parent::save();

        // Calculer le hash APRES l'insert (inclut l'ID)
        $hash = $this->computeHash($config, $previousHash);
        $this->attributes[$config['hashColumn']] = $hash;

        // Mettre a jour le hash dans la ligne (atomic)
        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->update([$config['hashColumn'] => $hash]);

        $this->original[$config['hashColumn']] = $hash;

        Event::dispatch(static::class . '.nf525.created', [
            'model' => $this,
            'number' => $sequence,
            'hash' => $hash,
        ]);

        return $result;
    }

    /**
     * Override delete() — interdit la suppression.
     */
    public function delete(): bool
    {
        throw new \RuntimeException('NF525 — suppression interdite. Utilisez createCredit() pour annuler.');
    }

    /**
     * Cree un avoir (credit note) referençant ce document.
     */
    public function createCredit(string $reason, ?array $overrides = null): static
    {
        $config = self::getNf525Config();
        $data = $overrides ?? $this->toArray();

        // Inverser les montants numeriques
        foreach ($data as $key => $value) {
            if (is_numeric($value) && !in_array($key, [static::$primaryKey, $config['sequenceColumn'], $config['hashColumn'], $config['prevHashColumn']], true)) {
                $data[$key] = -abs((float) $value);
            }
        }

        // Nettoyer les colonnes internes
        unset(
            $data[static::$primaryKey],
            $data[$config['sequenceColumn']],
            $data[$config['hashColumn']],
            $data[$config['prevHashColumn']],
            $data[static::$createdAt],
            $data[static::$updatedAt],
        );

        $data['credit_of'] = $this->getKey();
        $data['credit_reason'] = $reason;
        $data['is_credit'] = true;

        $credit = new static($data);
        $credit->save();

        SecurityLogger::track('nf525.credit_created', [
            'original_id' => $this->getKey(),
            'credit_id' => $credit->getKey(),
            'reason' => $reason,
        ]);

        return $credit;
    }

    /**
     * Verifie l'integrite du hash de ce document.
     */
    public function verifyHash(): bool
    {
        $config = self::getNf525Config();
        $storedHash = $this->attributes[$config['hashColumn']] ?? '';
        $previousHash = $this->attributes[$config['prevHashColumn']] ?? '';

        $computedHash = $this->computeHash($config, $previousHash);

        return hash_equals($storedHash, $computedHash);
    }

    /**
     * Genere le numero sequentiel : PREFIX-YEAR-XXXXXX
     */
    private function generateSequenceNumber(array $config): string
    {
        $year = date('Y');
        $prefix = $config['prefix'];
        $column = $config['sequenceColumn'];
        $table = static::resolveTable();
        $connection = static::resolveConnection();

        $stmt = DB::raw(
            "SELECT {$column} FROM {$table} WHERE {$column} LIKE :pattern ORDER BY id DESC LIMIT 1",
            ['pattern' => "{$prefix}-{$year}-%"],
            $connection
        );

        $last = $stmt->fetchColumn();

        if ($last === false) {
            $next = 1;
        } else {
            $parts = explode('-', $last);
            $next = (int) end($parts) + 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $next);
    }

    /**
     * Recupere le hash du dernier enregistrement.
     */
    private function getLastHash(array $config): string
    {
        $table = static::resolveTable();
        $connection = static::resolveConnection();
        $hashCol = $config['hashColumn'];

        $stmt = DB::raw(
            "SELECT {$hashCol} FROM {$table} ORDER BY id DESC LIMIT 1",
            [],
            $connection
        );

        $hash = $stmt->fetchColumn();

        return $hash !== false ? (string) $hash : '0';
    }

    /**
     * Calcule le hash SHA-256 du document + hash precedent.
     */
    private function computeHash(array $config, string $previousHash): string
    {
        $data = $this->toArray();

        // Exclure les colonnes de hash + les colonnes configurees
        $exclude = array_merge(
            [$config['hashColumn'], $config['prevHashColumn']],
            $config['excludeFromHash']
        );

        foreach ($exclude as $col) {
            unset($data[$col]);
        }

        // Trier les cles pour un hash deterministe
        ksort($data);

        $payload = $previousHash . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);

        return hash('sha256', $payload);
    }

    /**
     * Lit et cache la config de l'attribut #[Nf525].
     *
     * @return array{prefix: string, sequenceColumn: string, hashColumn: string, prevHashColumn: string, excludeFromHash: string[]}
     */
    private static function getNf525Config(): array
    {
        $class = static::class;

        if (isset(self::$nf525ConfigCache[$class])) {
            return self::$nf525ConfigCache[$class];
        }

        $ref = new \ReflectionClass($class);
        $attrs = $ref->getAttributes(Nf525::class);

        if (empty($attrs)) {
            self::$nf525ConfigCache[$class] = [
                'prefix' => 'FA',
                'sequenceColumn' => 'number',
                'hashColumn' => 'hash',
                'prevHashColumn' => 'previous_hash',
                'excludeFromHash' => [],
            ];

            return self::$nf525ConfigCache[$class];
        }

        $instance = $attrs[0]->newInstance();
        self::$nf525ConfigCache[$class] = [
            'prefix' => $instance->prefix,
            'sequenceColumn' => $instance->sequenceColumn,
            'hashColumn' => $instance->hashColumn,
            'prevHashColumn' => $instance->prevHashColumn,
            'excludeFromHash' => $instance->excludeFromHash,
        ];

        return self::$nf525ConfigCache[$class];
    }
}
