<?php

namespace Fennec\Core\Feature;

use Fennec\Core\DB;

class FeatureFlagBuilder
{
    private ?int $userId = null;
    private ?string $role = null;

    public function __construct(
        private string $key,
    ) {
    }

    /**
     * Filtre par utilisateur.
     */
    public function whenUser(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Filtre par role.
     */
    public function whenRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Evalue si le feature flag est actif selon les regles et le contexte.
     */
    public function enabled(): bool
    {
        $row = $this->getFlag();

        if ($row === null) {
            return false;
        }

        // Flag desactive globalement
        if (!(bool) $row['enabled']) {
            return false;
        }

        // Pas de regles = actif pour tous
        $rules = $row['rules'] ?? null;
        if ($rules === null || $rules === '') {
            return true;
        }

        $rules = is_string($rules) ? json_decode($rules, true) : $rules;

        if (!is_array($rules) || empty($rules)) {
            return true;
        }

        return $this->evaluateRules($rules);
    }

    private function evaluateRules(array $rules): bool
    {
        // Regle users : liste d'IDs autorises
        if (isset($rules['users']) && is_array($rules['users'])) {
            if ($this->userId !== null && in_array($this->userId, $rules['users'], true)) {
                return true;
            }
        }

        // Regle roles : liste de roles autorises
        if (isset($rules['roles']) && is_array($rules['roles'])) {
            if ($this->role !== null && in_array($this->role, $rules['roles'], true)) {
                return true;
            }
        }

        // Regle percentage : pourcentage d'activation
        if (isset($rules['percentage'])) {
            $percentage = (int) $rules['percentage'];
            if ($this->userId !== null) {
                // Hash deterministe par user
                return (crc32($this->key . ':' . $this->userId) % 100) < $percentage;
            }

            return random_int(0, 99) < $percentage;
        }

        // Si des regles existent mais aucune ne match, refuser
        if (isset($rules['users']) || isset($rules['roles'])) {
            return false;
        }

        return true;
    }

    private function getFlag(): ?array
    {
        try {
            return DB::table('feature_flags')->where('key', $this->key)->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
