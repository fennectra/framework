<?php

namespace App\Controllers;

use App\Dto\Webhook\WebhookDeliveryItem;
use App\Dto\Webhook\WebhookItem;
use App\Dto\Webhook\WebhookListRequest;
use App\Dto\Webhook\WebhookResponse;
use App\Dto\Webhook\WebhookStoreRequest;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Queue\Job;
use Fennec\Core\Webhook\WebhookDeliveryJob;
use Fennec\Core\Webhook\WebhookManager;

class WebhookController
{
    // ─── CRUD ──────────────────────────────────────────────────

    #[ApiDescription('Lister les webhooks', 'Retourne la liste paginee des webhooks.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function index(WebhookListRequest $request): array
    {
        if ($request->is_active !== null) {
            $items = Webhook::where('is_active', '=', $request->is_active)
                ->orderBy('created_at', 'DESC')
                ->get();

            return [
                'data' => array_map(fn ($item) => $item->toArray(), $items),
                'meta' => ['total' => count($items)],
            ];
        }

        return Webhook::paginate($request->limit, $request->page);
    }

    #[ApiDescription('Afficher un webhook')]
    #[ApiStatus(200, 'Webhook trouve')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function show(string $id): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
        );
    }

    #[ApiDescription('Creer un webhook')]
    #[ApiStatus(201, 'Webhook cree')]
    public function store(WebhookStoreRequest $input): WebhookResponse
    {
        $secret = $input->secret ?? bin2hex(random_bytes(32));

        $item = Webhook::create([
            'name' => $input->name,
            'url' => $input->url,
            'secret' => $secret,
            'events' => json_encode($input->events),
            'is_active' => $input->is_active,
            'description' => $input->description,
        ]);

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: 'Webhook cree avec succes',
        );
    }

    #[ApiDescription('Modifier un webhook')]
    #[ApiStatus(200, 'Webhook modifie')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function update(string $id, WebhookStoreRequest $input): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);

        $data = [
            'name' => $input->name,
            'url' => $input->url,
            'events' => json_encode($input->events),
            'is_active' => $input->is_active,
            'description' => $input->description,
        ];

        if ($input->secret !== null) {
            $data['secret'] = $input->secret;
        }

        $item->fill($data)->save();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: 'Webhook modifie avec succes',
        );
    }

    #[ApiDescription('Supprimer un webhook')]
    #[ApiStatus(200, 'Webhook supprime')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function delete(string $id): array
    {
        $item = Webhook::findOrFail((int) $id);
        $item->delete();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return [
            'status' => 'ok',
            'message' => 'Webhook supprime avec succes',
        ];
    }

    #[ApiDescription('Activer ou desactiver un webhook')]
    #[ApiStatus(200, 'Statut modifie')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function toggle(string $id): WebhookResponse
    {
        $item = Webhook::findOrFail((int) $id);
        $newStatus = !$item->getAttribute('is_active');
        $item->fill(['is_active' => $newStatus])->save();

        // Vider le cache du WebhookManager
        try {
            WebhookManager::getInstance()->clearCache();
        } catch (\Throwable) {
            // WebhookManager non initialise — ignorer
        }

        return new WebhookResponse(
            status: 'ok',
            data: new WebhookItem(...$item->toArray()),
            message: $newStatus ? 'Webhook active' : 'Webhook desactive',
        );
    }

    // ─── Deliveries ────────────────────────────────────────────

    #[ApiDescription('Lister les livraisons d\'un webhook')]
    #[ApiStatus(200, 'Liste retournee')]
    #[ApiStatus(404, 'Webhook non trouve')]
    public function deliveries(string $id): array
    {
        $webhook = Webhook::findOrFail((int) $id);
        $limit = (int) ($_GET['limit'] ?? 20);
        $page = (int) ($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $deliveries = WebhookDelivery::where('webhook_id', '=', (int) $id)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'data' => array_map(fn ($d) => $d->toArray(), $deliveries),
            'meta' => [
                'webhook_id' => (int) $id,
                'webhook_name' => $webhook->getAttribute('name'),
                'page' => $page,
                'limit' => $limit,
            ],
        ];
    }

    #[ApiDescription('Statistiques de livraison', 'Vue d\'ensemble des livraisons par webhook et totaux globaux.')]
    #[ApiStatus(200, 'Statistiques retournees')]
    public function deliveryStats(): array
    {
        return [
            'status' => 'ok',
            'data' => [
                'overview' => WebhookDelivery::statsOverview(),
                'by_webhook' => Webhook::stats(),
            ],
        ];
    }

    #[ApiDescription('Echecs recents', 'Liste des dernieres livraisons echouees.')]
    #[ApiStatus(200, 'Liste retournee')]
    public function recentFailures(): array
    {
        $limit = (int) ($_GET['limit'] ?? 20);

        return [
            'status' => 'ok',
            'data' => WebhookDelivery::recentFailures($limit),
        ];
    }

    #[ApiDescription('Relancer une livraison echouee')]
    #[ApiStatus(200, 'Livraison relancee')]
    #[ApiStatus(404, 'Livraison non trouvee')]
    #[ApiStatus(422, 'Livraison non echouee')]
    public function retry(string $id): array
    {
        $delivery = WebhookDelivery::findOrFail((int) $id);

        if ($delivery->getAttribute('status') !== 'failed') {
            throw new HttpException(422, 'Seules les livraisons echouees peuvent etre relancees');
        }

        $webhook = Webhook::findOrFail((int) $delivery->getAttribute('webhook_id'));

        Job::dispatch(WebhookDeliveryJob::class, [
            'webhook_id' => (int) $webhook->getAttribute('id'),
            'url' => $webhook->getAttribute('url'),
            'secret' => $webhook->getAttribute('secret'),
            'event' => $delivery->getAttribute('event'),
            'payload' => json_decode($delivery->getAttribute('payload') ?? '{}', true),
            'attempt' => 0,
        ], 'webhooks');

        return [
            'status' => 'ok',
            'message' => 'Livraison relancee via la queue webhooks',
        ];
    }
}