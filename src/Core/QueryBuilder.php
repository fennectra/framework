<?php

namespace Fennec\Core;

class QueryBuilder
{
    private array $select = ['*'];
    private array $wheres = [];
    private array $orderBy = [];
    private array $joins = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $params = [];
    private int $paramIndex = 0;

    public function __construct(
        private Database $db,
        private string $table,
    ) {
    }

    /**
     * Colonnes à sélectionner.
     */
    public function select(string ...$columns): self
    {
        $this->select = $columns;

        return $this;
    }

    /**
     * Condition WHERE. Supporte 2 ou 3 arguments :
     *   where('active', true)         → active = true
     *   where('age', '>', 18)         → age > 18
     *   where('name', 'LIKE', '%Jo%') → name LIKE '%Jo%'
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $placeholder = $this->addParam($value);
        $this->wheres[] = ['type' => 'AND', 'clause' => "{$column} {$operator} {$placeholder}"];

        return $this;
    }

    /**
     * Condition OR WHERE.
     */
    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $placeholder = $this->addParam($value);
        $this->wheres[] = ['type' => 'OR', 'clause' => "{$column} {$operator} {$placeholder}"];

        return $this;
    }

    /**
     * Condition WHERE IN.
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $v) {
            $placeholders[] = $this->addParam($v);
        }
        $in = implode(', ', $placeholders);
        $this->wheres[] = ['type' => 'AND', 'clause' => "{$column} IN ({$in})"];

        return $this;
    }

    /**
     * Condition WHERE IS NULL.
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'AND', 'clause' => "{$column} IS NULL"];

        return $this;
    }

    /**
     * Condition WHERE IS NOT NULL.
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['type' => 'AND', 'clause' => "{$column} IS NOT NULL"];

        return $this;
    }

    /**
     * JOIN.
     */
    public function join(string $table, string $col1, string $operator, string $col2, string $type = 'JOIN'): self
    {
        $this->joins[] = "{$type} {$table} ON {$col1} {$operator} {$col2}";

        return $this;
    }

    /**
     * LEFT JOIN.
     */
    public function leftJoin(string $table, string $col1, string $operator, string $col2): self
    {
        return $this->join($table, $col1, $operator, $col2, 'LEFT JOIN');
    }

    /**
     * ORDER BY.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$direction}";

        return $this;
    }

    /**
     * LIMIT.
     */
    public function limit(int $n): self
    {
        $this->limit = $n;

        return $this;
    }

    /**
     * OFFSET.
     */
    public function offset(int $n): self
    {
        $this->offset = $n;

        return $this;
    }

    // --- Terminal methods ---

    /**
     * Exécute SELECT et retourne toutes les lignes.
     */
    public function get(): array
    {
        $sql = $this->buildSelect();

        return $this->db->query($sql, $this->params)->fetchAll();
    }

    /**
     * Retourne la première ligne, optionnellement hydratée en DTO.
     */
    public function first(?string $dtoClass = null): mixed
    {
        $this->limit = 1;
        $sql = $this->buildSelect();
        $row = $this->db->query($sql, $this->params)->fetch();

        if (!$row) {
            return null;
        }

        if ($dtoClass !== null) {
            return new $dtoClass(...$row);
        }

        return $row;
    }

    /**
     * Retourne le nombre de lignes.
     */
    public function count(): int
    {
        $savedSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        $sql = $this->buildSelect();
        $this->select = $savedSelect;

        $result = $this->db->query($sql, $this->params)->fetch();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Vérifie si au moins une ligne existe.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * INSERT et retourne le last insert ID.
     */
    public function insert(array $data): string|false
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = [];

        foreach ($data as $value) {
            $placeholders[] = $this->addParam($value);
        }

        $values = implode(', ', $placeholders);
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";

        $this->db->query($sql, $this->params);

        return $this->db->getConnection()->lastInsertId();
    }

    /**
     * UPDATE et retourne le nombre de lignes affectées.
     */
    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $column => $value) {
            $placeholder = $this->addParam($value);
            $sets[] = "{$column} = {$placeholder}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        return $this->db->query($sql, $this->params)->rowCount();
    }

    /**
     * DELETE et retourne le nombre de lignes affectées.
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhere();

        return $this->db->query($sql, $this->params)->rowCount();
    }

    // --- SQL builders ---

    private function buildSelect(): string
    {
        $columns = implode(', ', $this->select);
        $sql = "SELECT {$columns} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhere();

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        foreach ($this->wheres as $i => $where) {
            if ($i === 0) {
                $sql .= $where['clause'];
            } else {
                $sql .= " {$where['type']} {$where['clause']}";
            }
        }

        return $sql;
    }

    private function addParam(mixed $value): string
    {
        $key = ':qb_p' . $this->paramIndex++;
        $this->params[$key] = $value;

        return $key;
    }
}
