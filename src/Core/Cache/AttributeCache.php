<?php

namespace Fennec\Core\Cache;

class AttributeCache
{
    private const CACHE_KEY = 'attributes';

    /** Taille max du cache memoire pour eviter les fuites en mode worker. */
    private const MAX_CACHE_SIZE = 500;

    /** @var array<string, array> Cache mémoire pour la requête courante */
    private static array $memory = [];

    public function __construct(
        private FileCache $cache,
    ) {
        // Charger le cache fichier en mémoire au démarrage
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            self::$memory = $cached;
        }
    }

    /**
     * Récupère les attributs d'une méthode ou classe (avec cache).
     *
     * @param string $className
     * @param string|null $method   Null pour les attributs de classe
     * @param string $attributeClass  Classe de l'attribut à chercher
     * @return array<object> Instances d'attributs
     */
    public function get(string $className, ?string $method, string $attributeClass): array
    {
        $key = "{$className}::{$method}::{$attributeClass}";

        if (isset(self::$memory[$key])) {
            return self::$memory[$key];
        }

        // Lecture via reflection
        if ($method !== null) {
            $ref = new \ReflectionMethod($className, $method);
        } else {
            $ref = new \ReflectionClass($className);
        }

        $instances = [];
        foreach ($ref->getAttributes($attributeClass) as $attr) {
            $instances[] = $attr->newInstance();
        }

        // Eviction FIFO si le cache depasse la taille max
        if (count(self::$memory) >= self::MAX_CACHE_SIZE) {
            reset(self::$memory);
            unset(self::$memory[key(self::$memory)]);
        }

        self::$memory[$key] = $instances;

        return $instances;
    }

    /**
     * Persiste le cache mémoire vers le fichier.
     */
    public function persist(): void
    {
        // On ne peut pas serialiser les objets d'attributs, donc on ne persiste que les clés
        // Le cache mémoire suffit pour la durée de la requête
        // Le cache fichier est reconstruit au cache:clear
    }

    /**
     * Vide le cache.
     */
    public function clear(): void
    {
        self::$memory = [];
        $this->cache->clear(self::CACHE_KEY);
    }

    /**
     * Retourne le nombre d'entrees dans le cache memoire.
     */
    public static function size(): int
    {
        return count(self::$memory);
    }
}
