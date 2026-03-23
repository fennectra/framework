<?php

namespace App\Mail\Organization;

use Fennec\Core\Mail\Mailable;

class MemberWelcome extends Mailable
{
    public string $templateName = 'organization_welcome';

    public function __construct(
        string $to,
        private readonly string $name,
        private readonly string $orgName,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'name' => $this->name,
            'org_name' => $this->orgName,
        ];
    }

    public static function defaultSubjectEn(): string
    {
        return 'Welcome to {{org_name}}!';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Welcome, {{name}}!</h1>'
            . '<p>You are now a member of <strong>{{org_name}}</strong>.</p>'
            . '<p>You can start collaborating with your team right away.</p>';
    }
}