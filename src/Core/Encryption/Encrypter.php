<?php

namespace Fennec\Core\Encryption;

use Fennec\Core\Env;

/**
 * Service de chiffrement AES-256-GCM pour les champs sensibles.
 *
 * Usage :
 *   $cipher = Encrypter::encrypt('secret data');   // 'enc:base64...'
 *   $plain  = Encrypter::decrypt($cipher);          // 'secret data'
 */
class Encrypter
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:';
    private const TAG_LENGTH = 16;

    private static ?string $key = null;

    public static function setKey(string $key): void
    {
        self::$key = $key;
    }

    /**
     * Chiffre une valeur avec AES-256-GCM.
     */
    public static function encrypt(string $value): string
    {
        $key = self::resolveKey();
        $iv = random_bytes(12);
        $tag = '';

        $encrypted = openssl_encrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Format: enc:base64(iv + tag + ciphertext)
        return self::PREFIX . base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Dechiffre une valeur. Retourne la valeur telle quelle si pas chiffree.
     */
    public static function decrypt(string $value): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }

        $key = self::resolveKey();
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid encrypted payload');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, self::TAG_LENGTH);
        $ciphertext = substr($raw, 12 + self::TAG_LENGTH);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: invalid key or tampered data');
        }

        return $decrypted;
    }

    /**
     * Verifie si une valeur est chiffree (prefixe enc:).
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private static function resolveKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $envKey = Env::get('ENCRYPTION_KEY', '');

        if ($envKey === '') {
            throw new \RuntimeException('ENCRYPTION_KEY not set in .env');
        }

        // Decode base64 key (32 bytes for AES-256)
        $decoded = base64_decode($envKey, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new \RuntimeException('ENCRYPTION_KEY must be 32 bytes base64-encoded');
        }

        self::$key = $decoded;

        return self::$key;
    }

    /**
     * Genere une cle de chiffrement aleatoire (base64).
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
