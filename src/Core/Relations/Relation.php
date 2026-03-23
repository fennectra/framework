<?php

namespace Fennec\Core\Relations;

use Fennec\Core\Collection;
use Fennec\Core\Model;

abstract class Relation
{
    public function __construct(
        protected Model $parent,
        protected string $relatedClass,
        protected string $foreignKey,
        protected string $localKey,
    ) {
    }

    /**
     * Execute la requete et retourne le resultat (lazy loading).
     */
    abstract public function resolve(): Model|Collection|null;

    /**
     * Charge la relation en batch pour une collection de models (eager loading).
     */
    abstract public function eagerLoadOnto(Collection $models, string $relationName): void;
}
