<?php

namespace App\Controllers;

use App\Dto\Audit\AuditLogItem;
use App\Dto\Audit\AuditLogListRequest;
use App\Dto\Audit\AuditLogResponse;
use App\Models\AuditLog;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;

class AuditController
{
    #[ApiDescription('Lister les logs d\'audit', 'Retourne la liste paginee des logs d\'audit avec filtres.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(AuditLogListRequest $request): array
    {
        $filters = [];

        if ($request->action !== null) {
            $filters['action'] = $request->action;
        }

        if ($request->auditable_type !== null) {
            $filters['auditable_type'] = $request->auditable_type;
        }

        if ($request->user_id !== null) {
            $filters['user_id'] = $request->user_id;
        }

        if ($request->date_from !== null) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->date_to !== null) {
            $filters['date_to'] = $request->date_to;
        }

        $offset = ($request->page - 1) * $request->limit;
        $data = AuditLog::search($filters, $request->limit, $offset);

        return [
            'status' => 'ok',
            'data' => $data,
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
            ],
        ];
    }

    #[ApiDescription('Afficher un log d\'audit')]
    #[ApiStatus(200, 'Entree trouvee')]
    #[ApiStatus(404, 'Entree non trouvee')]
    public function show(string $id): AuditLogResponse
    {
        $item = AuditLog::findOrFail((int) $id);

        return new AuditLogResponse(
            status: 'ok',
            data: new AuditLogItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Logs d\'audit pour une entite', 'Retourne tous les logs d\'audit pour un type et ID d\'entite donnes.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function forEntity(string $type, string $entityId): array
    {
        $data = AuditLog::forEntity($type, (int) $entityId);

        return [
            'status' => 'ok',
            'data' => array_map(fn ($item) => $item->toArray(), $data),
        ];
    }

    #[ApiDescription('Logs d\'audit par utilisateur', 'Retourne les logs d\'audit pour un utilisateur donne.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function byUser(string $userId): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);
        $offset = (int) ($_GET['offset'] ?? 0);

        $data = AuditLog::byUser((int) $userId, $limit, $offset);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Statistiques d\'audit', 'Retourne le nombre d\'actions par type (created/updated/deleted).')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function stats(): array
    {
        return [
            'status' => 'ok',
            'data' => AuditLog::stats(),
        ];
    }

    #[ApiDescription('Activite recente', 'Retourne les dernieres entrees du journal d\'audit.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function recentActivity(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);

        return [
            'status' => 'ok',
            'data' => AuditLog::recentActivity($limit),
        ];
    }
}