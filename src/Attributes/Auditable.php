<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Auditable
{
    /**
     * @param string[] $only   Colonnes a auditer (vide = toutes)
     * @param string[] $except Colonnes a exclure de l'audit
     */
    public function __construct(
        public array $only = [],
        public array $except = [],
    ) {
    }
}
