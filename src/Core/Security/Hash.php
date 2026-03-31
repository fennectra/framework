<?php

namespace Fennec\Core\Security;

use Fennec\Core\Env;

/**
 * Password hashing facade with configurable algorithm.
 *
 * The algorithm is configured via the PASSWORD_HASH_ALGO environment variable.
 * Supported values: bcrypt, argon2i, argon2id (default: bcrypt).
 *
 * Usage:
 *   $hash = Hash::make('my-password');
 *   $valid = Hash::verify('my-password', $hash);
 *   $needs = Hash::needsRehash($hash);  // true if algo changed since hash was created
 */
class Hash
{
    private static array $algorithmMap = [
        'bcrypt' => PASSWORD_BCRYPT,
        'argon2i' => PASSWORD_ARGON2I,
        'argon2id' => PASSWORD_ARGON2ID,
    ];

    /**
     * Hash a password using the configured algorithm.
     */
    public static function make(string $password): string
    {
        $algo = self::algorithm();
        $options = self::options($algo);

        return password_hash($password, $algo, $options);
    }

    /**
     * Verify a password against a hash.
     *
     * This method is algorithm-agnostic: it works regardless of which
     * algorithm was used to create the hash, allowing seamless migration
     * between algorithms.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (algorithm or cost changed).
     *
     * Call this after a successful login to transparently upgrade hashes
     * when the configured algorithm changes:
     *
     *   if (Hash::verify($password, $user->password)) {
     *       if (Hash::needsRehash($user->password)) {
     *           $user->update(['password' => Hash::make($password)]);
     *       }
     *   }
     */
    public static function needsRehash(string $hash): bool
    {
        $algo = self::algorithm();
        $options = self::options($algo);

        return password_needs_rehash($hash, $algo, $options);
    }

    /**
     * Return the configured PHP password algorithm constant.
     */
    public static function algorithm(): int|string
    {
        $name = strtolower(Env::get('PASSWORD_HASH_ALGO', 'bcrypt'));

        if (!isset(self::$algorithmMap[$name])) {
            $supported = implode(', ', array_keys(self::$algorithmMap));
            throw new \RuntimeException(
                "Unsupported password hash algorithm '{$name}'. Supported: {$supported}."
            );
        }

        return self::$algorithmMap[$name];
    }

    /**
     * Return the configured algorithm name (bcrypt, argon2i, argon2id).
     */
    public static function algorithmName(): string
    {
        return strtolower(Env::get('PASSWORD_HASH_ALGO', 'bcrypt'));
    }

    /**
     * Build options array for the configured algorithm.
     */
    private static function options(int|string $algo): array
    {
        if ($algo === PASSWORD_BCRYPT) {
            $cost = (int) Env::get('PASSWORD_HASH_COST', '12');

            return ['cost' => max(4, min(31, $cost))];
        }

        if ($algo === PASSWORD_ARGON2I || $algo === PASSWORD_ARGON2ID) {
            return [
                'memory_cost' => (int) Env::get('PASSWORD_HASH_MEMORY', (string) PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                'time_cost' => (int) Env::get('PASSWORD_HASH_TIME', (string) PASSWORD_ARGON2_DEFAULT_TIME_COST),
                'threads' => (int) Env::get('PASSWORD_HASH_THREADS', (string) PASSWORD_ARGON2_DEFAULT_THREADS),
            ];
        }

        return [];
    }
}
