<?php

namespace App\Dto\Audit;

use Fennec\Attributes\Description;

readonly class AuditLogItem
{
    public function __construct(
        #[Description('Identifiant unique')]
        public int $id,
        #[Description('Type de l\'entite auditee')]
        public string $auditable_type,
        #[Description('ID de l\'entite auditee')]
        public int $auditable_id,
        #[Description('Action effectuee (created/updated/deleted)')]
        public string $action,
        #[Description('Anciennes valeurs')]
        public mixed $old_values = null,
        #[Description('Nouvelles valeurs')]
        public mixed $new_values = null,
        #[Description('ID de l\'utilisateur')]
        public ?int $user_id = null,
        #[Description('Adresse IP')]
        public ?string $ip_address = null,
        #[Description('ID de la requete')]
        public ?string $request_id = null,
        #[Description('Date de creation')]
        public ?string $created_at = null,
    ) {
    }
}