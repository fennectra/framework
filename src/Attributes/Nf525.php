<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Nf525
{
    /**
     * @param string   $prefix          Prefixe des numeros (FA, AV, etc.)
     * @param string   $sequenceColumn  Colonne du numero sequentiel
     * @param string   $hashColumn      Colonne du hash SHA-256
     * @param string   $prevHashColumn  Colonne du hash precedent
     * @param string[] $excludeFromHash Colonnes exclues du hash
     */
    public function __construct(
        public string $prefix = 'FA',
        public string $sequenceColumn = 'number',
        public string $hashColumn = 'hash',
        public string $prevHashColumn = 'previous_hash',
        public array $excludeFromHash = [],
    ) {
    }
}
