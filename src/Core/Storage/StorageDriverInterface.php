<?php

namespace Fennec\Core\Storage;

interface StorageDriverInterface
{
    /**
     * Stocke un fichier.
     */
    public function put(string $path, string $contents): bool;

    /**
     * Lit le contenu d'un fichier.
     */
    public function get(string $path): ?string;

    /**
     * Verifie si un fichier existe.
     */
    public function exists(string $path): bool;

    /**
     * Supprime un fichier.
     */
    public function delete(string $path): bool;

    /**
     * Retourne l'URL publique d'un fichier.
     */
    public function url(string $path): string;

    /**
     * Copie un fichier.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Deplace un fichier.
     */
    public function move(string $from, string $to): bool;

    /**
     * Retourne la taille en octets.
     */
    public function size(string $path): ?int;

    /**
     * Liste les fichiers dans un repertoire.
     */
    public function files(string $directory = ''): array;

    /**
     * Retourne le chemin absolu du fichier (local uniquement).
     * Retourne null si le driver ne supporte pas l'acces direct (S3).
     */
    public function absolutePath(string $path): ?string;
}
