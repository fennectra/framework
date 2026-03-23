<?php

namespace Fennec\Core\Relations;

use Fennec\Core\Collection;

class HasMany extends Relation
{
    public function resolve(): Collection
    {
        $relatedClass = $this->relatedClass;
        $rows = $relatedClass::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->get();

        return new Collection(array_map(fn ($row) => $relatedClass::hydrate($row), $rows));
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
                $model->setRelation($relationName, new Collection([]));
            }

            return;
        }

        $relatedClass = $this->relatedClass;
        $rows = $relatedClass::query()
            ->whereIn($this->foreignKey, $localValues)
            ->get();

        // Grouper par foreign key
        $grouped = [];
        foreach ($rows as $row) {
            $related = $relatedClass::hydrate($row);
            $fk = $related->getAttribute($this->foreignKey);
            $grouped[$fk][] = $related;
        }

        foreach ($models as $model) {
            $lk = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, new Collection($grouped[$lk] ?? []));
        }
    }
}
