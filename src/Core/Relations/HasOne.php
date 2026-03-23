<?php

namespace Fennec\Core\Relations;

use Fennec\Core\Collection;
use Fennec\Core\Model;

class HasOne extends Relation
{
    public function resolve(): ?Model
    {
        $relatedClass = $this->relatedClass;
        $row = $relatedClass::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->first();

        return $row ? $relatedClass::hydrate($row) : null;
    }

    public function eagerLoadOnto(Collection $models, string $relationName): void
    {
        $localValues = [];
        foreach ($models as $model) {
            $lk = $model->getAttribute($this->localKey);
            if ($lk !== null) {
                $localValues[$lk] = true;
            }
        }
        $localValues = array_keys($localValues);

        if (empty($localValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, null);
            }

            return;
        }

        $relatedClass = $this->relatedClass;
        $rows = $relatedClass::query()
            ->whereIn($this->foreignKey, $localValues)
            ->get();

        $relatedMap = [];
        foreach ($rows as $row) {
            $related = $relatedClass::hydrate($row);
            $fk = $related->getAttribute($this->foreignKey);
            if (!isset($relatedMap[$fk])) {
                $relatedMap[$fk] = $related;
            }
        }

        foreach ($models as $model) {
            $lk = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $relatedMap[$lk] ?? null);
        }
    }
}
