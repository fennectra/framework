<?php

namespace Fennec\Attributes;

/**
 * Indique le type des éléments d'un tableau pour la documentation OpenAPI.
 *
 * Usage : #[ArrayOf(MonDto::class)] sur une propriété de type array.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class ArrayOf
{
    public function __construct(
        public string $className,
    ) {
    }
}
