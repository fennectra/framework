<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class StateMachine
{
    /** @var array<string, string[]> Parsed transitions: state => [allowed targets] */
    public array $parsed = [];

    /**
     * @param string   $column      Colonne du model qui contient l'etat
     * @param string[] $transitions Liste de transitions "from->to"
     */
    public function __construct(
        public string $column = 'status',
        public array $transitions = [],
    ) {
        $this->parsed = self::parseTransitions($transitions);
    }

    /**
     * Parse les transitions "from->to" en map indexee.
     *
     * @return array<string, string[]>
     */
    public static function parseTransitions(array $transitions): array
    {
        $map = [];

        foreach ($transitions as $transition) {
            $parts = array_map('trim', explode('->', $transition, 2));

            if (count($parts) !== 2) {
                continue;
            }

            [$from, $to] = $parts;
            $map[$from] ??= [];

            if (!in_array($to, $map[$from], true)) {
                $map[$from][] = $to;
            }
        }

        return $map;
    }
}
