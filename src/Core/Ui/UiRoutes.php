<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Router;
use Fennec\Middleware\UiAuthMiddleware;

class UiRoutes
{
    public static function register(Router $router): void
    {
        // Login — pas de middleware, c'est le point d'entree
        $router->post('/api/ui/login', [UiController::class, 'login']);

        // Routes protegees — super admin only
        $router->group([
            'prefix' => '/api/ui',
            'description' => 'Fennec UI Dashboard API (super admin only)',
            'middleware' => [[UiAuthMiddleware::class]],
        ], function (Router $router) {
            // Dashboard
            $router->get('/dashboard', [UiController::class, 'dashboard']);

            // NF525
            $router->get('/nf525/invoices', [UiController::class, 'nf525Invoices']);
            $router->get('/nf525/closings', [UiController::class, 'nf525Closings']);
            $router->post('/nf525/verify', [UiController::class, 'nf525Verify']);
            $router->post('/nf525/close', [UiController::class, 'nf525Close']);
            $router->get('/nf525/fec', [UiController::class, 'nf525Fec']);

            // Audit
            $router->get('/audit/logs', [UiController::class, 'auditLogs']);
            $router->get('/audit/stats', [UiController::class, 'auditStats']);
            $router->post('/audit/purge', [UiController::class, 'auditPurge']);

            // Security
            $router->get('/security/events', [UiController::class, 'securityEvents']);
            $router->get('/security/lockouts', [UiController::class, 'securityLockouts']);
            $router->post('/security/unlock', [UiController::class, 'securityUnlock']);

            // Worker
            $router->get('/worker', [UiController::class, 'workerStats']);
            $router->post('/worker/restart', [UiController::class, 'workerRestart']);

            // Webhooks
            $router->get('/webhooks', [UiController::class, 'webhookList']);
            $router->post('/webhooks', [UiController::class, 'webhookCreate']);
            $router->put('/webhooks/{id}', [UiController::class, 'webhookUpdate']);
            $router->delete('/webhooks/{id}', [UiController::class, 'webhookDelete']);
            $router->get('/webhooks/{id}/deliveries', [UiController::class, 'webhookDeliveries']);
        });
    }
}
