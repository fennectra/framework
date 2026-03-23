<?php

namespace Fennec\Core;

/**
 * Collection typée pour les résultats ORM.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
class Collection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /** @param array<int, T> $items */
    public function __construct(
        private array $items = [],
    ) {
    }

    /**
     * Premier élément ou null.
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Dernier élément ou null.
     * @return T|null
     */
    public function last(): mixed
    {
        return $this->items ? end($this->items) : null;
    }

    /**
     * Nombre d'éléments.
     */
    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Applique un callback sur chaque élément.
     * @return Collection<mixed>
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Filtre les éléments.
     * @return Collection<T>
     */
    public function filter(callable $callback): self
    {
        return new self(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Réduit la collection à une valeur unique.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Exécute un callback sur chaque élément (sans retour).
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Extrait une colonne/propriété de chaque élément.
     * @return Collection<mixed>
     */
    public function pluck(string $key): self
    {
        return new self(array_map(fn ($item) => is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null), $this->items));
    }

    /**
     * Indexe la collection par une clé.
     * @return array<string|int, T>
     */
    public function keyBy(string $key): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($k !== null) {
                $result[$k] = $item;
            }
        }

        return $result;
    }

    /**
     * Vérifie si un élément existe par callback.
     */
    public function contains(callable $callback): bool
    {
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trie la collection.
     * @return Collection<T>
     */
    public function sortBy(string $key, string $direction = 'ASC'): self
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $direction) {
            $va = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
            $vb = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);
            $cmp = $va <=> $vb;

            return strtoupper($direction) === 'DESC' ? -$cmp : $cmp;
        });

        return new self($items);
    }

    /**
     * Limite le nombre d'éléments.
     */
    public function take(int $n): self
    {
        return new self(array_slice($this->items, 0, $n));
    }

    /**
     * Retourne le tableau brut.
     * @return array<int, T>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Convertit tous les éléments en array (appelle toArray() si disponible).
     */
    public function toArray(): array
    {
        return array_map(fn ($item) => $item instanceof Model ? $item->toArray() : $item, $this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
