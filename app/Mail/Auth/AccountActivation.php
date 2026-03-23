<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class AccountActivation extends Mailable
{
    public string $templateName = 'account_activation';

    public function __construct(
        string $to,
        private string $name,
        private string $activationUrl,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'activation_url' => $this->activationUrl,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Activez votre compte';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Activate your account';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bonjour {{name}}</h1><p>Cliquez sur le lien ci-dessous pour activer votre compte :</p><p><a href="{{activation_url}}">Activer mon compte</a></p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Hello {{name}}</h1><p>Click the link below to activate your account:</p><p><a href="{{activation_url}}">Activate my account</a></p>';
    }
}