<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Env;

#[Command('make:organization', 'Generate organization module: multi-org SaaS with invitations')]
class MakeOrganizationCommand implements CommandInterface
{
    private string $appDir;
    private array $created = [];

    public function execute(array $args): int
    {
        $this->appDir = FENNEC_BASE_PATH . '/app';

        echo "\n\033[1;36m  ╔══════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m  ║   Organization — Multi-org SaaS module        ║\033[0m\n";
        echo "\033[1;36m  ╚══════════════════════════════════════════════╝\033[0m\n\n";

        // 1. Migrations
        $this->createMigrations();

        // 2. Models
        $this->createOrganizationModel();
        $this->createOrganizationMemberModel();
        $this->createOrganizationInvitationModel();

        // 3. DTOs
        $this->createDto('OrganizationStoreRequest', $this->dtoOrganizationStoreRequest());
        $this->createDto('OrganizationResponse', $this->dtoOrganizationResponse());
        $this->createDto('InviteMemberRequest', $this->dtoInviteMemberRequest());
        $this->createDto('OrganizationMemberResponse', $this->dtoOrganizationMemberResponse());

        // 4. Controller
        $this->createController();

        // 5. Mail templates
        $this->createMailTemplate('Invitation', $this->mailInvitation());
        $this->createMailTemplate('MemberWelcome', $this->mailMemberWelcome());

        // 6. Routes
        $this->createRoutes();

        // Summary
        echo "\n\033[1;32m  ✓ Organization module generated successfully\033[0m\n\n";

        foreach ($this->created as $file) {
            echo "    \033[32m✓\033[0m {$file}\n";
        }

        echo "\n  \033[33mAPI Routes (authenticated) :\033[0m\n";
        echo "    GET    /organizations                              List my organizations\n";
        echo "    POST   /organizations                              Create organization\n";
        echo "    GET    /organizations/{id}                         Show organization details\n";
        echo "    PUT    /organizations/{id}                         Update organization\n";
        echo "    DELETE /organizations/{id}                         Delete organization\n";
        echo "    POST   /organizations/{id}/invite                  Invite member\n";
        echo "    POST   /organizations/{id}/members/{memberId}/role Update member role\n";
        echo "    DELETE /organizations/{id}/members/{memberId}      Remove member\n";
        echo "\n  \033[33mPublic Routes :\033[0m\n";
        echo "    GET    /organizations/invitations/{token}/accept   Accept invitation\n";
        echo "\n\033[36m  Run: ./forge migrate\033[0m\n\n";

        return 0;
    }

    // ─── Migrations ─────────────────────────────────────────────

    private function createMigrations(): void
    {
        $dir = FENNEC_BASE_PATH . '/database/migrations';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $driver = Env::get('DB_DRIVER', 'pgsql');

        // Migration 1: organizations table
        $this->createSingleMigration($dir, 'create_organizations', $driver, 'organizations');

        // Migration 2: organization_members table
        $this->createSingleMigration($dir, 'create_organization_members', $driver, 'organization_members');

        // Migration 3: organization_invitations table
        $this->createSingleMigration($dir, 'create_organization_invitations', $driver, 'organization_invitations');
    }

    private function createSingleMigration(string $dir, string $name, string $driver, string $table): void
    {
        foreach (glob($dir . '/*.php') as $file) {
            if (str_contains($file, $name)) {
                echo "  \033[33m⚠ Migration {$name} already exists, skipped\033[0m\n";

                return;
            }
        }

        $timestamp = date('Y_m_d_His');

        // Add a small offset to ensure unique timestamps
        if ($table === 'organization_members') {
            $timestamp = date('Y_m_d_His', strtotime('+1 second'));
        } elseif ($table === 'organization_invitations') {
            $timestamp = date('Y_m_d_His', strtotime('+2 seconds'));
        }

        $filename = "{$timestamp}_{$name}";

        $up = match ($driver) {
            'mysql' => $this->mysqlUp($table),
            'sqlite' => $this->sqliteUp($table),
            default => $this->pgsqlUp($table),
        };

        $down = "DROP TABLE IF EXISTS {$table}";

        $lines = [
            '<?php',
            '',
            'return [',
            '    \'up\' => \'' . str_replace("'", "\\'", $up) . '\',',
            '    \'down\' => \'' . $down . '\',',
            '];',
            '',
        ];

        file_put_contents("{$dir}/{$filename}.php", implode("\n", $lines));
        $this->created[] = "database/migrations/{$filename}.php";
    }

    // ─── Models ──────────────────────────────────────────────────

    private function createOrganizationModel(): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/Organization.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model Organization already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organizations')]
class Organization extends Model
{
    /**
     * Get all members of this organization.
     */
    public function members(): array
    {
        return OrganizationMember::where('organization_id', '=', $this->id)->get();
    }

    /**
     * Get the owner of this organization.
     */
    public function owner(): ?Model
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $this->owner_id]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? User::hydrate($row) : null;
    }

    /**
     * Find an organization by its slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM organizations WHERE slug = :slug LIMIT 1',
            ['slug' => $slug]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }

    /**
     * Generate a unique slug from a name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (static::findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get organizations for a given user (as owner or member).
     */
    public static function forUser(int $userId): array
    {
        $stmt = DB::raw(
            'SELECT o.* FROM organizations o '
            . 'INNER JOIN organization_members om ON om.organization_id = o.id '
            . 'WHERE om.user_id = :user_id '
            . 'ORDER BY o.created_at DESC',
            ['user_id' => $userId]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn ($row) => static::hydrate($row), $rows);
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/Organization.php';
    }

    private function createOrganizationMemberModel(): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/OrganizationMember.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model OrganizationMember already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organization_members')]
class OrganizationMember extends Model
{
    /**
     * Get the organization this member belongs to.
     */
    public function organization(): ?Organization
    {
        return Organization::find($this->organization_id);
    }

    /**
     * Get the user associated with this membership.
     */
    public function user(): ?Model
    {
        $stmt = DB::raw(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $this->user_id]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? User::hydrate($row) : null;
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/OrganizationMember.php';
    }

    private function createOrganizationInvitationModel(): void
    {
        $dir = "{$this->appDir}/Models";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/OrganizationInvitation.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Model OrganizationInvitation already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;

#[Table('organization_invitations')]
class OrganizationInvitation extends Model
{
    /**
     * Get the organization this invitation belongs to.
     */
    public function organization(): ?Organization
    {
        return Organization::find($this->organization_id);
    }

    /**
     * Find an invitation by its token.
     */
    public static function findByToken(string $token): ?static
    {
        $stmt = DB::raw(
            'SELECT * FROM organization_invitations WHERE token = :token LIMIT 1',
            ['token' => $token]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? static::hydrate($row) : null;
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Models/OrganizationInvitation.php';
    }

    // ─── DTOs ──────────────────────────────────────────────────

    private function createDto(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Dto/Organization";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ DTO {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Dto/Organization/{$name}.php";
    }

    private function dtoOrganizationStoreRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;
use Fennec\Attributes\Required;

readonly class OrganizationStoreRequest
{
    public function __construct(
        #[Required]
        #[Description('Organization name')]
        public string $name,
        #[Description('Organization description')]
        public ?string $description = null,
    ) {
    }
}
PHP;
    }

    private function dtoOrganizationResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;

readonly class OrganizationResponse
{
    public function __construct(
        #[Description('Unique identifier')]
        public int $id,
        #[Description('Organization name')]
        public string $name,
        #[Description('URL-friendly slug')]
        public string $slug,
        #[Description('Owner information')]
        public mixed $owner = null,
        #[Description('Number of members')]
        public int $members_count = 0,
        #[Description('Creation date')]
        public ?string $created_at = null,
    ) {
    }
}
PHP;
    }

    private function dtoInviteMemberRequest(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;
use Fennec\Attributes\Email;
use Fennec\Attributes\Required;

readonly class InviteMemberRequest
{
    public function __construct(
        #[Required]
        #[Email]
        #[Description('Email address of the person to invite')]
        public string $email,
        #[Description('Role to assign (owner, admin, member)')]
        public string $role = 'member',
    ) {
    }
}
PHP;
    }

    private function dtoOrganizationMemberResponse(): string
    {
        return <<<'PHP'
<?php

namespace App\Dto\Organization;

use Fennec\Attributes\Description;

readonly class OrganizationMemberResponse
{
    public function __construct(
        #[Description('Membership identifier')]
        public int $id,
        #[Description('User information')]
        public mixed $user = null,
        #[Description('Member role in the organization')]
        public string $role = 'member',
        #[Description('Date when the member joined')]
        public ?string $joined_at = null,
    ) {
    }
}
PHP;
    }

    // ─── Controller ────────────────────────────────────────────

    private function createController(): void
    {
        $dir = "{$this->appDir}/Controllers";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/OrganizationController.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Controller OrganizationController already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

namespace App\Controllers;

use App\Dto\Organization\InviteMemberRequest;
use App\Dto\Organization\OrganizationMemberResponse;
use App\Dto\Organization\OrganizationResponse;
use App\Dto\Organization\OrganizationStoreRequest;
use App\Mail\Organization\Invitation;
use App\Mail\Organization\MemberWelcome;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\Auth as AuthUser;
use Fennec\Core\HttpException;
use Fennec\Core\Mail\Mailer;
use Fennec\Core\Validator;

class OrganizationController
{
    #[ApiDescription('List my organizations', 'Returns all organizations the authenticated user belongs to.')]
    #[ApiStatus(200, 'List returned')]
    public function index(): array
    {
        $user = AuthUser::user();
        $orgs = Organization::forUser($user->id);

        $data = array_map(function ($org) {
            $members = $org->members();

            return new OrganizationResponse(
                id: (int) $org->id,
                name: $org->name,
                slug: $org->slug,
                owner: $org->owner()?->toArray(),
                members_count: count($members),
                created_at: $org->created_at,
            );
        }, $orgs);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Create an organization', 'Creates a new organization and sets the authenticated user as owner.')]
    #[ApiStatus(201, 'Organization created')]
    #[ApiStatus(422, 'Validation error')]
    public function store(OrganizationStoreRequest $request): array
    {
        Validator::validate($request);

        $user = AuthUser::user();
        $slug = Organization::generateSlug($request->name);

        $org = Organization::create([
            'name' => $request->name,
            'slug' => $slug,
            'owner_id' => $user->id,
            'description' => $request->description,
        ]);

        // Add the creator as owner member
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'status' => 'ok',
            'data' => $org->toArray(),
        ];
    }

    #[ApiDescription('Show organization details', 'Returns organization details including its members.')]
    #[ApiStatus(200, 'Organization found')]
    #[ApiStatus(404, 'Organization not found')]
    public function show(string $id): array
    {
        $org = Organization::findOrFail((int) $id);
        $members = $org->members();

        $memberResponses = array_map(function ($member) {
            return new OrganizationMemberResponse(
                id: (int) $member->id,
                user: $member->user()?->toArray(),
                role: $member->role,
                joined_at: $member->joined_at,
            );
        }, $members);

        return [
            'status' => 'ok',
            'data' => new OrganizationResponse(
                id: (int) $org->id,
                name: $org->name,
                slug: $org->slug,
                owner: $org->owner()?->toArray(),
                members_count: count($members),
                created_at: $org->created_at,
            ),
            'members' => $memberResponses,
        ];
    }

    #[ApiDescription('Update an organization', 'Updates organization name and/or description. Owner or admin only.')]
    #[ApiStatus(200, 'Organization updated')]
    #[ApiStatus(403, 'Forbidden')]
    #[ApiStatus(404, 'Organization not found')]
    public function update(string $id, OrganizationStoreRequest $request): array
    {
        Validator::validate($request);

        $org = Organization::findOrFail((int) $id);
        $user = AuthUser::user();

        $member = OrganizationMember::where('organization_id', '=', $org->id)
            ->where('user_id', '=', $user->id)
            ->first();

        if (!$member || !in_array($member->role, ['owner', 'admin'], true)) {
            throw new HttpException(403, 'Only the owner or an admin can update this organization.');
        }

        $org->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $org->toArray(),
        ];
    }

    #[ApiDescription('Delete an organization', 'Permanently deletes an organization. Owner only.')]
    #[ApiStatus(200, 'Organization deleted')]
    #[ApiStatus(403, 'Forbidden')]
    #[ApiStatus(404, 'Organization not found')]
    public function delete(string $id): array
    {
        $org = Organization::findOrFail((int) $id);
        $user = AuthUser::user();

        if ((int) $org->owner_id !== (int) $user->id) {
            throw new HttpException(403, 'Only the owner can delete this organization.');
        }

        $org->delete();

        return [
            'status' => 'ok',
            'message' => 'Organization deleted.',
        ];
    }

    #[ApiDescription('Invite a member', 'Sends an invitation email to join the organization.')]
    #[ApiStatus(200, 'Invitation sent')]
    #[ApiStatus(403, 'Forbidden')]
    #[ApiStatus(404, 'Organization not found')]
    #[ApiStatus(422, 'Validation error')]
    public function invite(string $id, InviteMemberRequest $request): array
    {
        Validator::validate($request);

        $org = Organization::findOrFail((int) $id);
        $user = AuthUser::user();

        $member = OrganizationMember::where('organization_id', '=', $org->id)
            ->where('user_id', '=', $user->id)
            ->first();

        if (!$member || !in_array($member->role, ['owner', 'admin'], true)) {
            throw new HttpException(403, 'Only the owner or an admin can invite members.');
        }

        $token = bin2hex(random_bytes(32));

        OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => $request->email,
            'role' => $request->role,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $invitationUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/')
            . '/organizations/invitations/' . $token . '/accept';

        $mail = new Invitation(
            to: $request->email,
            orgName: $org->name,
            inviterName: $user->name ?? $user->email ?? 'A team member',
            role: $request->role,
            invitationUrl: $invitationUrl,
        );

        Mailer::send($mail);

        return [
            'status' => 'ok',
            'message' => 'Invitation sent to ' . $request->email,
        ];
    }

    #[ApiDescription('Accept an invitation', 'Accepts an organization invitation via token and joins the organization.')]
    #[ApiStatus(200, 'Invitation accepted')]
    #[ApiStatus(400, 'Invalid or expired invitation')]
    public function acceptInvitation(string $token): array
    {
        $invitation = OrganizationInvitation::findByToken($token);

        if (!$invitation) {
            throw new HttpException(400, 'Invalid invitation token.');
        }

        if (strtotime($invitation->expires_at) < time()) {
            throw new HttpException(400, 'This invitation has expired.');
        }

        $user = AuthUser::user();
        $org = Organization::findOrFail((int) $invitation->organization_id);

        // Check if user is already a member
        $existing = OrganizationMember::where('organization_id', '=', $org->id)
            ->where('user_id', '=', $user->id)
            ->first();

        if ($existing) {
            throw new HttpException(400, 'You are already a member of this organization.');
        }

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $invitation->role,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        // Delete the used invitation
        $invitation->delete();

        // Send welcome email
        $welcome = new MemberWelcome(
            to: $user->email,
            name: $user->name ?? $user->email,
            orgName: $org->name,
        );

        Mailer::send($welcome);

        return [
            'status' => 'ok',
            'message' => 'You have joined ' . $org->name,
        ];
    }

    #[ApiDescription('Remove a member', 'Removes a member from the organization. Owner or admin only.')]
    #[ApiStatus(200, 'Member removed')]
    #[ApiStatus(403, 'Forbidden')]
    #[ApiStatus(404, 'Member not found')]
    public function removeMember(string $id, string $memberId): array
    {
        $org = Organization::findOrFail((int) $id);
        $user = AuthUser::user();

        $currentMember = OrganizationMember::where('organization_id', '=', $org->id)
            ->where('user_id', '=', $user->id)
            ->first();

        if (!$currentMember || !in_array($currentMember->role, ['owner', 'admin'], true)) {
            throw new HttpException(403, 'Only the owner or an admin can remove members.');
        }

        $targetMember = OrganizationMember::findOrFail((int) $memberId);

        if ((int) $targetMember->organization_id !== (int) $org->id) {
            throw new HttpException(404, 'Member not found in this organization.');
        }

        if ($targetMember->role === 'owner') {
            throw new HttpException(403, 'Cannot remove the organization owner.');
        }

        $targetMember->delete();

        return [
            'status' => 'ok',
            'message' => 'Member removed from organization.',
        ];
    }

    #[ApiDescription('Update member role', 'Changes the role of a member. Owner only.')]
    #[ApiStatus(200, 'Role updated')]
    #[ApiStatus(403, 'Forbidden')]
    #[ApiStatus(404, 'Member not found')]
    public function updateMemberRole(string $id, string $memberId): array
    {
        $org = Organization::findOrFail((int) $id);
        $user = AuthUser::user();

        if ((int) $org->owner_id !== (int) $user->id) {
            throw new HttpException(403, 'Only the owner can change member roles.');
        }

        $targetMember = OrganizationMember::findOrFail((int) $memberId);

        if ((int) $targetMember->organization_id !== (int) $org->id) {
            throw new HttpException(404, 'Member not found in this organization.');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $newRole = $body['role'] ?? null;

        if (!in_array($newRole, ['admin', 'member'], true)) {
            throw new HttpException(422, 'Invalid role. Allowed: admin, member.');
        }

        $targetMember->update(['role' => $newRole]);

        return [
            'status' => 'ok',
            'data' => $targetMember->toArray(),
        ];
    }
}
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Controllers/OrganizationController.php';
    }

    // ─── Mail Templates ────────────────────────────────────────

    private function createMailTemplate(string $name, string $content): void
    {
        $dir = "{$this->appDir}/Mail/Organization";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/{$name}.php";

        if (file_exists($file)) {
            echo "  \033[33m⚠ Mail template {$name} already exists, skipped\033[0m\n";

            return;
        }

        file_put_contents($file, $content);
        $this->created[] = "app/Mail/Organization/{$name}.php";
    }

    private function mailInvitation(): string
    {
        return <<<'PHP'
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
PHP;
    }

    private function mailMemberWelcome(): string
    {
        return <<<'PHP'
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
PHP;
    }

    // ─── Routes ────────────────────────────────────────────────

    private function createRoutes(): void
    {
        $dir = "{$this->appDir}/Routes";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/organizations.php";
        if (file_exists($file)) {
            echo "  \033[33m⚠ Routes organizations.php already exists, skipped\033[0m\n";

            return;
        }

        $content = <<<'PHP'
<?php

use App\Controllers\OrganizationController;
use App\Middleware\Auth;

// ─── Public : Invitation acceptance ────────────────────────────
$router->get('/organizations/invitations/{token}/accept', [OrganizationController::class, 'acceptInvitation']);

// ─── Authenticated : Organization management ───────────────────
$router->group([
    'prefix' => '/organizations',
    'description' => 'Organization — Multi-org SaaS management',
    'middleware' => [[Auth::class]],
], function ($router) {
    $router->get('', [OrganizationController::class, 'index']);
    $router->post('', [OrganizationController::class, 'store']);
    $router->get('/{id}', [OrganizationController::class, 'show']);
    $router->put('/{id}', [OrganizationController::class, 'update']);
    $router->delete('/{id}', [OrganizationController::class, 'delete']);
    $router->post('/{id}/invite', [OrganizationController::class, 'invite']);
    $router->post('/{id}/members/{memberId}/role', [OrganizationController::class, 'updateMemberRole']);
    $router->delete('/{id}/members/{memberId}', [OrganizationController::class, 'removeMember']);
});
PHP;

        file_put_contents($file, $content);
        $this->created[] = 'app/Routes/organizations.php';
    }

    // ─── SQL Migrations — PostgreSQL ──────────────────────────

    private function pgsqlUp(string $table): string
    {
        return match ($table) {
            'organizations' => 'CREATE TABLE IF NOT EXISTS organizations ('
                . ' id BIGSERIAL PRIMARY KEY,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' slug VARCHAR(255) NOT NULL UNIQUE,'
                . ' owner_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
                . ' logo VARCHAR(500) DEFAULT NULL,'
                . ' description TEXT DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW(),'
                . ' updated_at TIMESTAMP DEFAULT NOW()'
                . ')',

            'organization_members' => 'CREATE TABLE IF NOT EXISTS organization_members ('
                . ' id BIGSERIAL PRIMARY KEY,'
                . ' organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,'
                . ' user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
                . ' role VARCHAR(20) NOT NULL DEFAULT \'member\','
                . ' invited_at TIMESTAMP DEFAULT NULL,'
                . ' joined_at TIMESTAMP DEFAULT NULL,'
                . ' UNIQUE(organization_id, user_id)'
                . ')',

            'organization_invitations' => 'CREATE TABLE IF NOT EXISTS organization_invitations ('
                . ' id BIGSERIAL PRIMARY KEY,'
                . ' organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,'
                . ' email VARCHAR(255) NOT NULL,'
                . ' role VARCHAR(20) NOT NULL DEFAULT \'member\','
                . ' token VARCHAR(64) NOT NULL UNIQUE,'
                . ' expires_at TIMESTAMP NOT NULL,'
                . ' created_at TIMESTAMP DEFAULT NOW()'
                . ')',
        };
    }

    // ─── SQL Migrations — MySQL ───────────────────────────────

    private function mysqlUp(string $table): string
    {
        return match ($table) {
            'organizations' => 'CREATE TABLE IF NOT EXISTS organizations ('
                . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' name VARCHAR(255) NOT NULL,'
                . ' slug VARCHAR(255) NOT NULL,'
                . ' owner_id BIGINT UNSIGNED NOT NULL,'
                . ' logo VARCHAR(500) DEFAULT NULL,'
                . ' description TEXT DEFAULT NULL,'
                . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . ' UNIQUE KEY unique_slug (slug),'
                . ' CONSTRAINT fk_org_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'organization_members' => 'CREATE TABLE IF NOT EXISTS organization_members ('
                . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' organization_id BIGINT UNSIGNED NOT NULL,'
                . ' user_id BIGINT UNSIGNED NOT NULL,'
                . ' role VARCHAR(20) NOT NULL DEFAULT \'member\','
                . ' invited_at TIMESTAMP NULL DEFAULT NULL,'
                . ' joined_at TIMESTAMP NULL DEFAULT NULL,'
                . ' UNIQUE KEY unique_org_user (organization_id, user_id),'
                . ' CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,'
                . ' CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'organization_invitations' => 'CREATE TABLE IF NOT EXISTS organization_invitations ('
                . ' id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' organization_id BIGINT UNSIGNED NOT NULL,'
                . ' email VARCHAR(255) NOT NULL,'
                . ' role VARCHAR(20) NOT NULL DEFAULT \'member\','
                . ' token VARCHAR(64) NOT NULL,'
                . ' expires_at TIMESTAMP NOT NULL,'
                . ' created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
                . ' UNIQUE KEY unique_token (token),'
                . ' CONSTRAINT fk_invitation_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        };
    }

    // ─── SQL Migrations — SQLite ──────────────────────────────

    private function sqliteUp(string $table): string
    {
        return match ($table) {
            'organizations' => 'CREATE TABLE IF NOT EXISTS organizations ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' name TEXT NOT NULL,'
                . ' slug TEXT NOT NULL UNIQUE,'
                . ' owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
                . ' logo TEXT DEFAULT NULL,'
                . ' description TEXT DEFAULT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
                . ' updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
                . ')',

            'organization_members' => 'CREATE TABLE IF NOT EXISTS organization_members ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,'
                . ' user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,'
                . ' role TEXT NOT NULL DEFAULT \'member\','
                . ' invited_at TEXT DEFAULT NULL,'
                . ' joined_at TEXT DEFAULT NULL,'
                . ' UNIQUE(organization_id, user_id)'
                . ')',

            'organization_invitations' => 'CREATE TABLE IF NOT EXISTS organization_invitations ('
                . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . ' organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,'
                . ' email TEXT NOT NULL,'
                . ' role TEXT NOT NULL DEFAULT \'member\','
                . ' token TEXT NOT NULL UNIQUE,'
                . ' expires_at TEXT NOT NULL,'
                . ' created_at TEXT DEFAULT CURRENT_TIMESTAMP'
                . ')',
        };
    }
}
