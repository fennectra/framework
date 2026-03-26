<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Response;

class UiOrganizationsController
{
    use UiHelper;

    public function list(): void
    {
        try {
            $result = $this->paginate('organizations');

            foreach ($result['data'] as &$org) {
                $org['members_count'] = (int) (DB::raw(
                    'SELECT COUNT(*) as cnt FROM organization_members WHERE organization_id = ?',
                    [$org['id']]
                )->fetchAll()[0]['cnt'] ?? 0);
            }

            Response::json($result);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function show(int $id): void
    {
        try {
            $org = DB::raw('SELECT * FROM organizations WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$org) {
                Response::json(['error' => 'Organization not found'], 404);

                return;
            }

            $org['members'] = DB::raw(
                'SELECT om.*, u.email, u.name
                 FROM organization_members om
                 JOIN users u ON om.user_id = u.id
                 WHERE om.organization_id = ?
                 ORDER BY om.id ASC',
                [$id]
            )->fetchAll();

            $org['invitations'] = DB::raw(
                'SELECT * FROM organization_invitations WHERE organization_id = ? ORDER BY id DESC',
                [$id]
            )->fetchAll();

            Response::json($org);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function members(int $id): void
    {
        try {
            $members = DB::raw(
                'SELECT om.*, u.email, u.name
                 FROM organization_members om
                 JOIN users u ON om.user_id = u.id
                 WHERE om.organization_id = ?
                 ORDER BY om.id ASC',
                [$id]
            )->fetchAll();

            Response::json($members);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function stats(): void
    {
        try {
            $total = (int) (DB::raw('SELECT COUNT(*) as cnt FROM organizations')->fetchAll()[0]['cnt'] ?? 0);
            $members = (int) (DB::raw('SELECT COUNT(*) as cnt FROM organization_members')->fetchAll()[0]['cnt'] ?? 0);
            $invitations = (int) (DB::raw(
                'SELECT COUNT(*) as cnt FROM organization_invitations WHERE accepted_at IS NULL'
            )->fetchAll()[0]['cnt'] ?? 0);

            Response::json([
                'organizations' => $total,
                'totalMembers' => $members,
                'pendingInvitations' => $invitations,
            ]);
        } catch (\Throwable) {
            Response::json(['organizations' => 0, 'totalMembers' => 0, 'pendingInvitations' => 0]);
        }
    }
}
