<?php

namespace App\Mail\Organization;

use Fennec\Core\Mail\Mailable;

class Invitation extends Mailable
{
    public string $templateName = 'organization_invitation';

    public function __construct(
        string $to,
        private readonly string $orgName,
        private readonly string $inviterName,
        private readonly string $role,
        private readonly string $invitationUrl,
    ) {
        $this->to = $to;
    }

    public function variables(): array
    {
        return [
            'org_name' => $this->orgName,
            'inviter_name' => $this->inviterName,
            'role' => $this->role,
            'invitation_url' => $this->invitationUrl,
        ];
    }

    public static function defaultSubjectEn(): string
    {
        return 'You have been invited to join {{org_name}}';
    }

    public static function defaultBodyEn(): string
    {
        return '<h1>Organization Invitation</h1>'
            . '<p>{{inviter_name}} has invited you to join <strong>{{org_name}}</strong> as <strong>{{role}}</strong>.</p>'
            . '<p><a href="{{invitation_url}}">Accept Invitation</a></p>'
            . '<p>This invitation will expire in 7 days.</p>';
    }
}