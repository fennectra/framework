<?php

namespace Fennec\Core;

use Fennec\Attributes\Table;
use Fennec\Core\Relations\BelongsTo;
use Fennec\Core\Relations\HasMany;
use Fennec\Core\Relations\HasOne;
use Fennec\Core\Relations\Relation;

/** @phpstan-consistent-constructor */
abstract class Model
{
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    /** @var array<string, Model|Collection|null> Relations chargees */
    protected array $_relations = [];

    // Defaults — overridable par les sous-classes
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static string $connection = 'default';
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;
    protected static string $createdAt = 'created_at';
    protected static string $updatedAt = 'updated_at';
    protected static string $deletedAt = 'deleted_at';

    /** @var array<string, string> Casting : column => type (int, float, bool, json, datetime, array) */
    protected static array $casts = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ─── Factory ──────────────────────────────────────────────

    /**
     * Crée une instance hydratée depuis une ligne DB (marque exists = true).
     */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;

        return $model;
    }

    // ─── Accesseurs magiques ──────────────────────────────────

    public function __get(string $name): mixed
    {
        // 1. Relation deja chargee (eager ou lazy cache)
        if (array_key_exists($name, $this->_relations)) {
            return $this->_relations[$name];
        }

        // 2. Methode relation existante → appeler et cacher
        if (method_exists($this, $name)) {
            $result = $this->$name();
            if ($result instanceof Relation) {
                $resolved = $result->resolve();
                $this->_relations[$name] = $resolved;

                return $resolved;
            }
        }

        // 3. Attribut classique
        return $this->getAttribute($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->castGet($key, $value);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->castSet($key, $value);
    }

    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    // ─── Casting ──────────────────────────────────────────────

    private function castGet(string $key, mixed $value): mixed
    {
        if ($value === null || !isset(static::$casts[$key])) {
            return $value;
        }

        return match (static::$casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable($value),
            default => $value,
        };
    }

    private function castSet(string $key, mixed $value): mixed
    {
        if ($value === null || !isset(static::$casts[$key])) {
            return $value;
        }

        return match (static::$casts[$key]) {
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            default => $value,
        };
    }

    // ─── Persistence ──────────────────────────────────────────

    /**
     * Sauvegarde le model (INSERT ou UPDATE selon exists).
     */
    public function save(): static
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    private function performInsert(): static
    {
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[static::$createdAt] = $now;
            $this->attributes[static::$updatedAt] = $now;
        }

        $id = static::query()->insert($this->attributes);
        $this->attributes[static::$primaryKey] = $id;
        $this->exists = true;
        $this->original = $this->attributes;

        Event::dispatch(static::class . '.created', $this);

        return $this;
    }

    private function performUpdate(): static
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return $this;
        }

        if (static::$timestamps) {
            $dirty[static::$updatedAt] = date('Y-m-d H:i:s');
            $this->attributes[static::$updatedAt] = $dirty[static::$updatedAt];
        }

        static::query()
            ->where(static::$primaryKey, $this->original[static::$primaryKey])
            ->update($dirty);

        $this->original = $this->attributes;

        Event::dispatch(static::class . '.updated', $this);

        return $this;
    }

    /**
     * Supprime le model (soft delete si activé).
     */
    public function delete(): bool
    {
        if (static::$softDeletes) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[static::$deletedAt] = $now;

            static::query()
                ->where(static::$primaryKey, $this->getKey())
                ->update([static::$deletedAt => $now]);

            Event::dispatch(static::class . '.deleted', $this);

            return true;
        }

        $result = static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->delete();

        $this->exists = false;

        Event::dispatch(static::class . '.deleted', $this);

        return $result > 0;
    }

    /**
     * Restaure un model soft-deleted.
     */
    public function restore(): bool
    {
        if (!static::$softDeletes) {
            return false;
        }

        $this->attributes[static::$deletedAt] = null;

        static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->update([static::$deletedAt => null]);

        return true;
    }

    /**
     * Suppression définitive (ignore soft delete).
     */
    public function forceDelete(): bool
    {
        $result = static::query()
            ->where(static::$primaryKey, $this->getKey())
            ->delete();

        $this->exists = false;

        return $result > 0;
    }

    // ─── Query statics ───────────────────────────────────────

    /**
     * Retourne un QueryBuilder pour cette table.
     */
    public static function query(): QueryBuilder
    {
        $table = static::resolveTable();
        $connection = static::resolveConnection();

        $qb = DB::table($table, $connection);

        // Exclure les soft-deleted par défaut
        if (static::$softDeletes) {
            $qb->whereNull(static::$deletedAt);
        }

        return $qb;
    }

    /**
     * Récupère tous les enregistrements.
     */
    public static function all(): Collection
    {
        $rows = static::query()->get();

        return new Collection(array_map(fn ($row) => static::hydrate($row), $rows));
    }

    /**
     * Compte le nombre d'enregistrements.
     */
    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Trouve par clé primaire.
     */
    public static function find(int|string $id): ?static
    {
        $row = static::query()
            ->where(static::$primaryKey, $id)
            ->first();

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Trouve par clé primaire ou lève une exception.
     */
    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new HttpException(404, static::class . " #{$id} non trouvé");
        }

        return $model;
    }

    /**
     * Conditions WHERE chainables retournant un ModelQueryBuilder.
     */
    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(static::class, static::query()))->where($column, $operatorOrValue, $value);
    }

    /**
     * Crée un nouveau model et le persiste.
     */
    public static function create(array $data): static
    {
        $model = new static($data);
        $model->save();

        return $model;
    }

    /**
     * Inclure les soft-deleted dans la requête.
     */
    public static function withTrashed(): ModelQueryBuilder
    {
        $table = static::resolveTable();
        $connection = static::resolveConnection();
        $qb = DB::table($table, $connection);

        return new ModelQueryBuilder(static::class, $qb);
    }

    /**
     * Uniquement les soft-deleted.
     */
    public static function onlyTrashed(): ModelQueryBuilder
    {
        $table = static::resolveTable();
        $connection = static::resolveConnection();
        $qb = DB::table($table, $connection);
        $qb->whereNotNull(static::$deletedAt);

        return new ModelQueryBuilder(static::class, $qb);
    }

    /**
     * Pagination native.
     */
    public static function paginate(int $perPage = 15, int $page = 1): array
    {
        return (new ModelQueryBuilder(static::class, static::query()))->paginate($perPage, $page);
    }

    // ─── Relations ────────────────────────────────────────────

    public function setRelation(string $name, Model|Collection|null $value): void
    {
        $this->_relations[$name] = $value;
    }

    public function getRelation(string $name): Model|Collection|null
    {
        return $this->_relations[$name] ?? null;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->_relations);
    }

    /**
     * Eager loading : User::with('role')->get()
     */
    public static function with(string ...$relations): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(static::class, static::query()))->with(...$relations);
    }

    /**
     * Relation hasMany : retourne un objet HasMany (lazy/eager).
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= $this->guessForeignKey();
        $localKey ??= static::$primaryKey;

        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    /**
     * Relation belongsTo : retourne un objet BelongsTo (lazy/eager).
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $ownerKey ??= $related::$primaryKey;
        $foreignKey ??= $this->guessBelongsToKey($related);

        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    /**
     * Relation hasOne : retourne un objet HasOne (lazy/eager).
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey ??= $this->guessForeignKey();
        $localKey ??= static::$primaryKey;

        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    // ─── Helpers ──────────────────────────────────────────────

    public function getKey(): int|string|null
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    /**
     * Retourne les attributs modifiés depuis le chargement.
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return array_key_exists($key, $this->getDirty());
        }

        return !empty($this->getDirty());
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this->attributes as $key => $value) {
            $data[$key] = $this->castGet($key, $value);
        }

        // Inclure les relations chargees (eager ou lazy)
        foreach ($this->_relations as $name => $related) {
            if ($related instanceof Collection) {
                $data[$name] = array_map(fn ($m) => $m->toArray(), $related->toArray());
            } elseif ($related instanceof Model) {
                $data[$name] = $related->toArray();
            } else {
                $data[$name] = null;
            }
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isDeleted(): bool
    {
        return static::$softDeletes && $this->attributes[static::$deletedAt] !== null;
    }

    // ─── Résolution table/connection ─────────────────────────

    /** @var array<string, string> Cache table par classe (évite pollution entre models) */
    private static array $_tableCache = [];

    protected static function resolveTable(): string
    {
        $class = static::class;

        if (isset(self::$_tableCache[$class])) {
            return self::$_tableCache[$class];
        }

        if (static::$table !== '') {
            self::$_tableCache[$class] = static::$table;

            return static::$table;
        }

        // Lire l'attribut #[Table]
        $ref = new \ReflectionClass($class);
        $attrs = $ref->getAttributes(Table::class);

        if (!empty($attrs)) {
            $tableAttr = $attrs[0]->newInstance();
            self::$_tableCache[$class] = $tableAttr->name;

            return self::$_tableCache[$class];
        }

        // Convention : NomDuModel → nom_du_models
        $short = $ref->getShortName();
        $resolved = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short)) . 's';
        self::$_tableCache[$class] = $resolved;

        return $resolved;
    }

    protected static function resolveConnection(): string
    {
        if (static::$connection !== 'default') {
            return static::$connection;
        }

        $ref = new \ReflectionClass(static::class);
        $attrs = $ref->getAttributes(Table::class);

        if (!empty($attrs)) {
            $tableAttr = $attrs[0]->newInstance();

            return $tableAttr->connection;
        }

        return 'default';
    }

    /**
     * Devine la clé étrangère : User → user_id
     */
    private function guessForeignKey(): string
    {
        $short = (new \ReflectionClass(static::class))->getShortName();

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short)) . '_id';
    }

    /**
     * Devine la clé belongsTo : Post->belongsTo(User) → user_id
     */
    private function guessBelongsToKey(string $related): string
    {
        $short = (new \ReflectionClass($related))->getShortName();

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short)) . '_id';
    }
}
