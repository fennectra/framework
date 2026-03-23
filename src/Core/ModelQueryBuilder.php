<?php

namespace Fennec\Core;

/**
 * Wrapper du QueryBuilder qui retourne des instances de Model au lieu de arrays.
 */
class ModelQueryBuilder
{
    /** @var string[] */
    private array $eagerLoad = [];

    public function __construct(
        private string $modelClass,
        private QueryBuilder $qb,
    ) {
    }

    /**
     * Eager load des relations : User::with('role', 'posts')->get()
     */
    public function with(string ...$relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);

        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->qb->where($column, $operatorOrValue, $value);

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->qb->orWhere($column, $operatorOrValue, $value);

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->qb->whereIn($column, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->qb->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->qb->whereNotNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->qb->orderBy($column, $direction);

        return $this;
    }

    public function limit(int $n): self
    {
        $this->qb->limit($n);

        return $this;
    }

    public function offset(int $n): self
    {
        $this->qb->offset($n);

        return $this;
    }

    public function select(string ...$columns): self
    {
        $this->qb->select(...$columns);

        return $this;
    }

    public function join(string $table, string $col1, string $operator, string $col2, string $type = 'JOIN'): self
    {
        $this->qb->join($table, $col1, $operator, $col2, $type);

        return $this;
    }

    public function leftJoin(string $table, string $col1, string $operator, string $col2): self
    {
        $this->qb->leftJoin($table, $col1, $operator, $col2);

        return $this;
    }

    // ─── Terminal methods (hydratent en Model) ────────────────

    /**
     * Retourne une Collection d'instances Model.
     */
    public function get(): Collection
    {
        $rows = $this->qb->get();
        $class = $this->modelClass;

        $collection = new Collection(array_map(fn ($row) => $class::hydrate($row), $rows));

        if (!empty($this->eagerLoad) && $collection->count() > 0) {
            $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    /**
     * Retourne la première instance ou null.
     */
    public function first(): ?Model
    {
        $row = $this->qb->first();

        if (!$row) {
            return null;
        }

        $class = $this->modelClass;
        $model = $class::hydrate($row);

        if (!empty($this->eagerLoad)) {
            $collection = new Collection([$model]);
            $this->eagerLoadRelations($collection);
        }

        return $model;
    }

    /**
     * Retourne la première instance ou lève une 404.
     */
    public function firstOrFail(): Model
    {
        $model = $this->first();

        if ($model === null) {
            throw new HttpException(404, $this->modelClass . ' non trouvé');
        }

        return $model;
    }

    public function count(): int
    {
        return $this->qb->count();
    }

    public function exists(): bool
    {
        return $this->qb->exists();
    }

    public function delete(): int
    {
        return $this->qb->delete();
    }

    public function update(array $data): int
    {
        return $this->qb->update($data);
    }

    /**
     * Pagination native : retourne data + meta.
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->qb->count();
        $lastPage = (int) ceil($total / $perPage);
        $page = max(1, min($page, $lastPage ?: 1));

        $rows = $this->qb
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $class = $this->modelClass;
        $items = array_map(fn ($row) => $class::hydrate($row), $rows);

        if (!empty($this->eagerLoad) && !empty($items)) {
            $collection = new Collection($items);
            $this->eagerLoadRelations($collection);
        }

        return [
            'data' => array_map(fn ($m) => $m->toArray(), $items),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    // ─── Eager Loading ──────────────────────────────────────

    private function eagerLoadRelations(Collection $models): void
    {
        $sample = $models->first();
        if (!$sample) {
            return;
        }

        foreach ($this->eagerLoad as $relationName) {
            if (!method_exists($sample, $relationName)) {
                continue;
            }

            $relation = $sample->$relationName();
            if ($relation instanceof \Fennec\Core\Relations\Relation) {
                $relation->eagerLoadOnto($models, $relationName);
            }
        }
    }
}
