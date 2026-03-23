<?php

namespace Fennec\Core\StateMachine;

/**
 * Trait pour les Models qui utilisent #[StateMachine].
 *
 * Usage :
 *   #[StateMachine(column: 'status', transitions: ['draft->pending', 'pending->approved'])]
 *   class Order extends Model {
 *       use HasStateMachine;
 *   }
 *
 *   $order->transitionTo('pending');
 *   $order->canTransitionTo('approved'); // true/false
 *   $order->currentState(); // 'pending'
 *   $order->availableTransitions(); // ['approved']
 */
trait HasStateMachine
{
    private static ?StateMachineEngine $smEngine = null;

    /**
     * Effectue la transition vers l'etat cible.
     */
    public function transitionTo(string $state): static
    {
        self::engine()->transition($this, $state);

        return $this;
    }

    /**
     * Verifie si la transition est possible.
     */
    public function canTransitionTo(string $state): bool
    {
        return self::engine()->canTransition($this, $state);
    }

    /**
     * Retourne l'etat courant.
     */
    public function currentState(): string
    {
        $config = (new \ReflectionClass(static::class))
            ->getAttributes(\Fennec\Attributes\StateMachine::class);

        $column = 'status';
        if (!empty($config)) {
            $column = $config[0]->newInstance()->column;
        }

        return (string) $this->getAttribute($column);
    }

    /**
     * Retourne les etats accessibles depuis l'etat courant.
     */
    public function availableTransitions(): array
    {
        return self::engine()->availableTransitions($this);
    }

    private static function engine(): StateMachineEngine
    {
        if (self::$smEngine === null) {
            self::$smEngine = new StateMachineEngine();
        }

        return self::$smEngine;
    }
}
