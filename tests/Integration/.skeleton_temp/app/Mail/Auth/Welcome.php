<?php

namespace App\Mail\Auth;

use Fennec\Core\Mail\Mailable;

class Welcome extends Mailable
{
    public string $templateName = 'welcome';

    public function __construct(
        string $to,
        private string $name,
        private string $service,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'service' => $this->service,
        ];
    }

    public static function defaultSubjectFr(): string
    {
        return 'Bienvenue sur {{service}} !';
    }

    public static function defaultSubjectEn(): string
    {
        return 'Welcome to {{service}}!';
    }

    public static function defaultBodyFr(): string
    {
        return '<h1>Bienvenue {{name}} !</h1><p>Votre compte sur {{service}} est maintenant actif.</p>';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Welcome {{name}}!</h1><p>Your account on {{service}} is now active.</p>';
    }
}