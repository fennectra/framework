<?php

namespace Fennec\Core\Relations;

use Fennec\Core\Collection;
use Fennec\Core\Model;

class BelongsTo extends Relation
{
    public function resolve(): ?Model
    {
        $fkValue = $this->parent->getAttribute($this->foreignKey);

        if ($fkValue === null) {
            return null;
        }

        return ($this->relatedClass)::find($fkValue);
    }

    public function eagerLoadOnto(Collection $models, string $relationName): void
    {
        $fkValues = [];
        foreach ($models as $model) {
            $fk = $model->getAttribute($this->foreignKey);
            if ($fk !== null) {
                $fkValues[$fk] = true;
            }
        }
        $fkValues = array_keys($fkValues);

        if (empty($fkValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, null);
            }

            return;
        }

        $relatedClass = $this->relatedClass;
        $rows = $relatedClass::query()
            ->whereIn($this->localKey, $fkValues)
            ->get();

        $relatedMap = [];
        foreach ($rows as $row) {
            $related = $relatedClass::hydrate($row);
            $relatedMap[$related->getAttribute($this->localKey)] = $related;
        }

        foreach ($models as $model) {
            $fk = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $relatedMap[$fk] ?? null);
        }
    }
}
