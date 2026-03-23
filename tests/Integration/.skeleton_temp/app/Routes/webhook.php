<?php

use App\Controllers\WebhookController;
use App\Middleware\Auth;

// ─── Admin : gestion des webhooks ──────────────────────────────
$router->group([
    'prefix' => '/webhooks',
    'description' => 'Webhooks — Gestion des webhooks sortants (admin)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    // Stats et failures avant les routes parametrees pour eviter les conflits
    $router->get('/stats', [WebhookController::class, 'deliveryStats']);
    $router->get('/failures', [WebhookController::class, 'recentFailures']);

    // CRUD
    $router->get('', [WebhookController::class, 'index']);
    $router->post('', [WebhookController::class, 'store']);
    $router->get('/{id}', [WebhookController::class, 'show']);
    $router->put('/{id}', [WebhookController::class, 'update']);
    $router->delete('/{id}', [WebhookController::class, 'delete']);

    // Actions
    $router->patch('/{id}/toggle', [WebhookController::class, 'toggle']);
    $router->get('/{id}/deliveries', [WebhookController::class, 'deliveries']);

    // Retry delivery
    $router->post('/deliveries/{id}/retry', [WebhookController::class, 'retry']);
});