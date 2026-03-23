<?php

namespace Fennec\Core\StateMachine;

use Fennec\Attributes\StateMachine;
use Fennec\Core\Event;
use Fennec\Core\Model;

class StateMachineEngine
{
    /** @var array<string, StateMachine|null> Cache par classe */
    private static array $cache = [];

    /** Taille max du cache pour eviter les fuites en mode worker. */
    private const MAX_CACHE_SIZE = 200;

    /**
     * Verifie si la transition vers l'etat cible est autorisee.
     */
    public function canTransition(Model $model, string $to): bool
    {
        $config = $this->getConfig($model);

        if ($config === null) {
            return false;
        }

        $current = $model->getAttribute($config->column);
        $allowed = $config->parsed[$current] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Effectue la transition : valide, met a jour, sauvegarde, dispatche l'evenement.
     */
    public function transition(Model $model, string $to): void
    {
        $config = $this->getConfig($model);

        if ($config === null) {
            throw new \RuntimeException(
                'Attribut #[StateMachine] absent sur ' . get_class($model)
            );
        }

        $current = $model->getAttribute($config->column);

        if (!$this->canTransition($model, $to)) {
            throw new \LogicException(
                "Transition invalide : {$current} -> {$to} sur " . get_class($model)
            );
        }

        $from = $current;

        $model->setAttribute($config->column, $to);
        $model->save();

        // Dispatch des evenements
        $class = get_class($model);
        Event::dispatch("{$class}.transition", [
            'model' => $model,
            'from' => $from,
            'to' => $to,
        ]);
        Event::dispatch("{$class}.transition.{$from}.{$to}", $model);
    }

    /**
     * Retourne les etats accessibles depuis l'etat courant.
     */
    public function availableTransitions(Model $model): array
    {
        $config = $this->getConfig($model);

        if ($config === null) {
            return [];
        }

        $current = $model->getAttribute($config->column);

        return $config->parsed[$current] ?? [];
    }

    /**
     * Lit et cache l'attribut #[StateMachine] de la classe du model.
     */
    private function getConfig(Model $model): ?StateMachine
    {
        $class = get_class($model);

        if (array_key_exists($class, self::$cache)) {
            return self::$cache[$class];
        }

        $ref = new \ReflectionClass($class);
        $attrs = $ref->getAttributes(StateMachine::class);

        if (empty($attrs)) {
            self::$cache[$class] = null;

            return null;
        }

        // Eviction FIFO si le cache depasse la taille max
        if (count(self::$cache) >= self::MAX_CACHE_SIZE) {
            reset(self::$cache);
            unset(self::$cache[key(self::$cache)]);
        }

        $config = $attrs[0]->newInstance();
        self::$cache[$class] = $config;

        return $config;
    }

    /**
     * Vide le cache (pour les tests ou reset worker).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Retourne le nombre d'entrees dans le cache.
     */
    public static function cacheSize(): int
    {
        return count(self::$cache);
    }
}
