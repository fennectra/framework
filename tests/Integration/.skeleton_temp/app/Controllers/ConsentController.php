<?php

namespace App\Controllers;

use App\Dto\Audit\ConsentObjectItem;
use App\Dto\Audit\ConsentObjectListRequest;
use App\Dto\Audit\ConsentObjectResponse;
use App\Dto\Audit\ConsentObjectStoreRequest;
use App\Dto\Audit\RgpdStatsResponse;
use App\Dto\Audit\UserConsentRequest;
use App\Middleware\Auth;
use App\Models\ConsentObject;
use App\Models\UserConsent;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;

class ConsentController
{
    // ─── Documents legaux (CRUD admin) ─────────────────────────

    #[ApiDescription('Lister les documents legaux', 'Retourne la liste paginee des documents RGPD.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(ConsentObjectListRequest $request): array
    {
        if ($request->key) {
            $items = ConsentObject::where('key', '=', $request->key)
                ->orderBy('object_version', 'DESC')
                ->get();

            return [
                'data' => array_map(fn ($item) => $item->toArray(), $items),
                'meta' => ['total' => count($items)],
            ];
        }

        return ConsentObject::paginate($request->limit, $request->page);
    }

    #[ApiDescription('Afficher un document legal')]
    #[ApiStatus(200, 'Document trouve')]
    #[ApiStatus(404, 'Document non trouve')]
    public function show(string $id): ConsentObjectResponse
    {
        $item = ConsentObject::findOrFail((int) $id);

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Derniere version d\'un document par cle', 'Retourne la derniere version active (cgu, legal, pcpd).')]
    #[ApiStatus(200, 'Document trouve')]
    #[ApiStatus(404, 'Document non trouve')]
    public function latest(string $key): ConsentObjectResponse
    {
        $item = ConsentObject::latestByKey($key);

        if (!$item) {
            throw new HttpException(404, 'Document non trouve pour la cle : ' . $key);
        }

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Creer une nouvelle version d\'un document legal')]
    #[ApiStatus(201, 'Document cree')]
    public function store(ConsentObjectStoreRequest $input): ConsentObjectResponse
    {
        $item = ConsentObject::createNewVersion(
            $input->key,
            $input->object_name,
            $input->object_content,
            $input->is_required,
        );

        return new ConsentObjectResponse(
            status: 'ok',
            data: new ConsentObjectItem(...$item->toArray()),
            message: 'Document legal cree (version ' . $item->getAttribute('object_version') . ')',
        );
    }

    // ─── Consentement utilisateur ──────────────────────────────

    #[ApiDescription('Donner son consentement', 'L\'utilisateur connecte accepte ou refuse un document legal.')]
    #[ApiStatus(200, 'Consentement enregistre')]
    #[ApiStatus(404, 'Document non trouve')]
    public function consent(UserConsentRequest $input): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $doc = ConsentObject::findOrFail($input->consent_object_id);

        $consent = UserConsent::recordConsent(
            userId: (int) $user['id'],
            consentObjectId: $input->consent_object_id,
            status: $input->consent_status,
            objectVersion: (int) $doc->getAttribute('object_version'),
            way: $input->consent_way,
        );

        return [
            'status' => 'ok',
            'message' => $input->consent_status ? 'Consentement accepte' : 'Consentement refuse',
            'data' => $consent->toArray(),
        ];
    }

    #[ApiDescription('Mon statut de consentement', 'Retourne le statut de consentement de l\'utilisateur connecte.')]
    #[ApiStatus(200, 'Statut retourne')]
    public function myConsents(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $userId = (int) $user['id'];

        return [
            'status' => 'ok',
            'data' => [
                'is_compliant' => UserConsent::hasAcceptedAll($userId),
                'consents' => UserConsent::userHistory($userId),
            ],
        ];
    }

    #[ApiDescription('Retirer tous mes consentements', 'Droit d\'opposition RGPD.')]
    #[ApiStatus(200, 'Consentements retires')]
    public function withdrawMyConsents(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Utilisateur non authentifie');
        }

        $count = UserConsent::withdrawAll((int) $user['id']);

        return [
            'status' => 'ok',
            'message' => $count . ' consentement(s) retire(s)',
        ];
    }

    // ─── DPO / Admin : statistiques et conformite ──────────────

    #[ApiDescription('Dashboard RGPD (DPO)', 'Tableau de bord avec taux de conformite, stats par document, utilisateurs non conformes.')]
    #[ApiStatus(200, 'Dashboard retourne')]
    public function dashboard(): RgpdStatsResponse
    {
        return new RgpdStatsResponse(
            status: 'ok',
            compliance: UserConsent::complianceRate(),
            documents: UserConsent::statsByDocument(),
            non_compliant_users: UserConsent::nonCompliantUsers(10),
        );
    }

    #[ApiDescription('Statistiques par document')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function stats(): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::statsByDocument(),
        ];
    }

    #[ApiDescription('Taux de conformite RGPD')]
    #[ApiStatus(200, 'Taux retourne')]
    public function complianceRate(): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::complianceRate(),
        ];
    }

    #[ApiDescription('Utilisateurs non conformes')]
    #[ApiStatus(200, 'Liste retournee')]
    public function nonCompliant(): array
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        return [
            'status' => 'ok',
            'data' => UserConsent::nonCompliantUsers($limit, $offset),
        ];
    }

    #[ApiDescription('Historique de consentement d\'un utilisateur', 'Droit d\'acces RGPD.')]
    #[ApiStatus(200, 'Historique retourne')]
    public function userHistory(string $userId): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::userHistory((int) $userId),
        ];
    }

    #[ApiDescription('Exporter les consentements d\'un utilisateur', 'Droit a la portabilite RGPD.')]
    #[ApiStatus(200, 'Export retourne')]
    public function exportUser(string $userId): array
    {
        return [
            'status' => 'ok',
            'data' => UserConsent::exportForUser((int) $userId),
        ];
    }

    #[ApiDescription('Retirer les consentements d\'un utilisateur', 'Droit a l\'oubli RGPD.')]
    #[ApiStatus(200, 'Consentements retires')]
    public function withdrawUser(string $userId): array
    {
        $count = UserConsent::withdrawAll((int) $userId);

        return [
            'status' => 'ok',
            'message' => $count . ' consentement(s) retire(s) pour l\'utilisateur #' . $userId,
        ];
    }
}