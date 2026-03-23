<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class PasswordReset extends Mailable
{
    public string $templateName = 'password_reset';

    public function __construct(
        string $to,
        private string $name,
        private string $resetUrl,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'reset_url' => $this->resetUrl,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Reinitialisation de votre mot de passe';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Reset your password';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bonjour {{name}}</h1><p>Cliquez sur le lien ci-dessous pour reinitialiser votre mot de passe :</p><p><a href="{{reset_url}}">Reinitialiser mon mot de passe</a></p><p>Ce lien expire dans 1 heure.</p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Hello {{name}}</h1><p>Click the link below to reset your password:</p><p><a href="{{reset_url}}">Reset my password</a></p><p>This link expires in 1 hour.</p>';
    }
}