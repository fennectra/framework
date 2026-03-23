<?php

namespace Fennec\Core\Nf525;

use Fennec\Core\DB;

/**
 * Verificateur d'integrite de la chaine de hash NF525.
 *
 * Parcourt tous les enregistrements et verifie que chaque hash
 * correspond au calcul attendu (hash precedent + donnees).
 */
class HashChainVerifier
{
    /**
     * Verifie l'integrite de la chaine de hash d'une table.
     *
     * @return array{valid: bool, total: int, errors: array<int, array<string, mixed>>}
     */
    public static function verify(
        string $table,
        string $hashColumn = 'hash',
        string $prevHashColumn = 'previous_hash',
        array $excludeFromHash = [],
        string $connection = 'default',
    ): array {
        $stmt = DB::raw(
            "SELECT * FROM {$table} ORDER BY id ASC",
            [],
            $connection
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $errors = [];
        $previousHash = '0';

        foreach ($rows as $row) {
            $storedHash = $row[$hashColumn] ?? '';
            $storedPrev = $row[$prevHashColumn] ?? '0';

            // Verifier que le previous_hash est coherent
            if ($storedPrev !== $previousHash) {
                $errors[] = [
                    'id' => $row['id'] ?? '?',
                    'error' => 'previous_hash_mismatch',
                    'expected_prev' => $previousHash,
                    'actual_prev' => $storedPrev,
                ];
            }

            // Recalculer le hash
            $data = $row;
            $exclude = array_merge([$hashColumn, $prevHashColumn], $excludeFromHash);
            foreach ($exclude as $col) {
                unset($data[$col]);
            }

            ksort($data);
            $payload = $storedPrev . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);
            $expectedHash = hash('sha256', $payload);

            if (!hash_equals($expectedHash, $storedHash)) {
                $errors[] = [
                    'id' => $row['id'] ?? '?',
                    'error' => 'hash_mismatch',
                    'expected' => $expectedHash,
                    'actual' => $storedHash,
                ];
            }

            $previousHash = $storedHash;
        }

        return [
            'valid' => empty($errors),
            'total' => count($rows),
            'errors' => $errors,
        ];
    }
}
