<?php

namespace Fennec\Core;

use Fennec\Attributes\Email;
use Fennec\Attributes\Max;
use Fennec\Attributes\MaxLength;
use Fennec\Attributes\Min;
use Fennec\Attributes\MinLength;
use Fennec\Attributes\Regex;
use Fennec\Attributes\Required;

class Validator
{
    /**
     * Valide des données contre un DTO (paramètres du constructeur + attributes).
     * Retourne un tableau d'erreurs (vide si valide).
     */
    public static function validate(string $className, array $data): array
    {
        $errors = [];
        $ref = new \ReflectionClass($className);
        $constructor = $ref->getConstructor();

        if (!$constructor) {
            return $errors;
        }

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $hasRequired = self::hasAttribute($param, Required::class);
            $value = $data[$name] ?? null;
            $exists = array_key_exists($name, $data);

            // Champ requis manquant
            if (!$exists) {
                if ($hasRequired || (!$type->allowsNull() && !$param->isDefaultValueAvailable())) {
                    $msg = self::getAttributeMessage($param, Required::class);
                    $errors[] = $msg ?: "{$name} est requis";
                }
                continue;
            }

            // Null sur un champ nullable → pas de validation supplémentaire
            if ($value === null && $type->allowsNull()) {
                continue;
            }

            // Vérification du type de base
            $expected = $type->getName();
            $valid = match ($expected) {
                'string' => is_string($value),
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'bool' => is_bool($value),
                'array' => is_array($value),
                default => true,
            };

            if (!$valid) {
                $errors[] = "{$name} doit être de type {$expected}";
                continue;
            }

            // Validation par attributes
            $errors = array_merge($errors, self::validateAttributes($param, $name, $value));
        }

        return $errors;
    }

    private static function validateAttributes(\ReflectionParameter $param, string $name, mixed $value): array
    {
        $errors = [];

        foreach ($param->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            match (true) {
                $instance instanceof Email => (function () use ($instance, $name, $value, &$errors) {
                    if (is_string($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = $instance->message ? "{$name} {$instance->message}" : "{$name} doit être une adresse email valide";
                    }
                })(),

                $instance instanceof MinLength => (function () use ($instance, $name, $value, &$errors) {
                    if (is_string($value) && mb_strlen($value) < $instance->min) {
                        $msg = $instance->message ?: "{$name} doit contenir au moins {$instance->min} caractères";
                        $errors[] = $msg;
                    }
                })(),

                $instance instanceof MaxLength => (function () use ($instance, $name, $value, &$errors) {
                    if (is_string($value) && mb_strlen($value) > $instance->max) {
                        $msg = $instance->message ?: "{$name} ne doit pas dépasser {$instance->max} caractères";
                        $errors[] = $msg;
                    }
                })(),

                $instance instanceof Min => (function () use ($instance, $name, $value, &$errors) {
                    if (is_numeric($value) && $value < $instance->min) {
                        $msg = $instance->message ?: "{$name} doit être supérieur ou égal à {$instance->min}";
                        $errors[] = $msg;
                    }
                })(),

                $instance instanceof Max => (function () use ($instance, $name, $value, &$errors) {
                    if (is_numeric($value) && $value > $instance->max) {
                        $msg = $instance->message ?: "{$name} doit être inférieur ou égal à {$instance->max}";
                        $errors[] = $msg;
                    }
                })(),

                $instance instanceof Regex => (function () use ($instance, $name, $value, &$errors) {
                    if (is_string($value) && !preg_match($instance->pattern, $value)) {
                        $errors[] = "{$name} {$instance->message}";
                    }
                })(),

                default => null,
            };
        }

        return $errors;
    }

    private static function hasAttribute(\ReflectionParameter $param, string $attributeClass): bool
    {
        return !empty($param->getAttributes($attributeClass));
    }

    private static function getAttributeMessage(\ReflectionParameter $param, string $attributeClass): string
    {
        $attrs = $param->getAttributes($attributeClass);
        if (empty($attrs)) {
            return '';
        }
        $instance = $attrs[0]->newInstance();

        return $instance->message ?? '';
    }
}
