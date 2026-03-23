<?php

namespace Fennec\Core\Encryption;

use Fennec\Attributes\Encrypted;

/**
 * Trait pour les Models avec des champs #[Encrypted].
 *
 * Chiffre automatiquement a l'ecriture et dechiffre a la lecture.
 *
 * Usage :
 *   class User extends Model {
 *       use HasEncryptedFields;
 *
 *       #[Encrypted]
 *       public string $phone;
 *   }
 */
trait HasEncryptedFields
{
    /** @var array<string, string[]> Cache par classe des colonnes chiffrees */
    private static array $encryptedFieldsCache = [];

    public function getAttribute(string $key): mixed
    {
        $value = parent::getAttribute($key);

        if ($value !== null && is_string($value) && in_array($key, self::getEncryptedFields(), true)) {
            try {
                return Encrypter::decrypt($value);
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($value !== null && is_string($value) && in_array($key, self::getEncryptedFields(), true)) {
            if (!Encrypter::isEncrypted($value)) {
                $value = Encrypter::encrypt($value);
            }
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Retourne les noms des proprietes marquees #[Encrypted].
     *
     * @return string[]
     */
    private static function getEncryptedFields(): array
    {
        $class = static::class;

        if (isset(self::$encryptedFieldsCache[$class])) {
            return self::$encryptedFieldsCache[$class];
        }

        $fields = [];
        $ref = new \ReflectionClass($class);

        foreach ($ref->getProperties() as $prop) {
            if (!empty($prop->getAttributes(Encrypted::class))) {
                $fields[] = $prop->getName();
            }
        }

        self::$encryptedFieldsCache[$class] = $fields;

        return $fields;
    }
}
