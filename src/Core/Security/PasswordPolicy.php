<?php

namespace Fennec\Core\Security;

use Fennec\Core\Env;

/**
 * Validation de la force des mots de passe (ISO 27001 A.8.5).
 *
 * Usage :
 *   $errors = PasswordPolicy::validate('MyP@ss123');
 *   PasswordPolicy::assertValid('weak'); // RuntimeException si invalide
 */
class PasswordPolicy
{
    /**
     * Valide un mot de passe selon la politique configuree.
     *
     * @return string[] Liste des erreurs (vide = valide)
     */
    public static function validate(string $password): array
    {
        $errors = [];
        $minLength = (int) Env::get('PASSWORD_MIN_LENGTH', '12');

        if (mb_strlen($password) < $minLength) {
            $errors[] = "Le mot de passe doit contenir au moins {$minLength} caracteres";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractere special';
        }

        // Mots de passe communs interdits
        if (self::isCommon($password)) {
            $errors[] = 'Ce mot de passe est trop courant';
        }

        return $errors;
    }

    /**
     * Valide et leve une exception si invalide.
     *
     * @throws \RuntimeException
     */
    public static function assertValid(string $password): void
    {
        $errors = self::validate($password);

        if (!empty($errors)) {
            throw new \RuntimeException(implode('. ', $errors));
        }
    }

    /**
     * Verifie le score de force (0-5).
     */
    public static function strength(string $password): int
    {
        $score = 0;

        if (mb_strlen($password) >= 8) {
            $score++;
        }
        if (mb_strlen($password) >= 12) {
            $score++;
        }
        if (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password)) {
            $score++;
        }
        if (preg_match('/[0-9]/', $password)) {
            $score++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score++;
        }

        return $score;
    }

    private static function isCommon(string $password): bool
    {
        $common = [
            'password', 'password123', '123456', '12345678', '123456789',
            'qwerty', 'abc123', 'letmein', 'admin', 'welcome',
            'monkey', 'master', 'dragon', 'login', 'princess',
            'football', 'shadow', 'sunshine', 'trustno1', 'iloveyou',
            'azerty', 'azerty123', 'motdepasse', 'bonjour',
        ];

        return in_array(strtolower($password), $common, true);
    }
}
