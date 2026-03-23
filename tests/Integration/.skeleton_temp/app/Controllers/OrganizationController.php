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