<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Router;
use Fennec\Middleware\UiAuthMiddleware;

class UiRoutes
{
    public static function register(Router $router): void
    {
        // ─── Public (no auth) ───

        $router->post('/ui/login', [UiController::class, 'login']);
        $router->post('/ui/refresh', [UiController::class, 'refresh']);

        // ─── Protected — super admin only ───

        $router->group([
            'prefix' => '/ui',
            'description' => 'Fennec UI Admin API',
            'middleware' => [[UiAuthMiddleware::class]],
        ], function (Router $router) {

            // ── Auth / Me ──
            $router->get('/me', [UiController::class, 'me']);

            // ── Dashboard ──
            $router->get('/dashboard', [UiController::class, 'dashboard']);

            // ── Users ──
            $router->group([
                'prefix' => '/users',
                'description' => 'User management',
            ], function (Router $router) {
                $router->get('', [UiUsersController::class, 'list']);
                $router->get('/{id}', [UiUsersController::class, 'show']);
                $router->put('/{id}', [UiUsersController::class, 'update']);
                $router->post('/{id}/toggle', [UiUsersController::class, 'toggle']);
                $router->post('/{id}/reset-password', [UiUsersController::class, 'resetPassword']);
                $router->delete('/{id}', [UiUsersController::class, 'delete']);
            });

            // ── Roles ──
            $router->group([
                'prefix' => '/roles',
                'description' => 'Role management',
            ], function (Router $router) {
                $router->get('', [UiUsersController::class, 'roles']);
                $router->post('', [UiUsersController::class, 'createRole']);
                $router->put('/{id}', [UiUsersController::class, 'updateRole']);
                $router->delete('/{id}', [UiUsersController::class, 'deleteRole']);
            });

            // ── Permissions ──
            $router->group([
                'prefix' => '/permissions',
                'description' => 'Permission management',
            ], function (Router $router) {
                $router->get('', [UiUsersController::class, 'permissions']);
                $router->post('', [UiUsersController::class, 'createPermission']);
                $router->delete('/{id}', [UiUsersController::class, 'deletePermission']);
            });

            // ── Feature Flags ──
            $router->group([
                'prefix' => '/feature-flags',
                'description' => 'Feature flag management',
            ], function (Router $router) {
                $router->get('', [UiFeatureFlagsController::class, 'list']);
                $router->post('', [UiFeatureFlagsController::class, 'create']);
                $router->put('/{id}', [UiFeatureFlagsController::class, 'update']);
                $router->delete('/{id}', [UiFeatureFlagsController::class, 'delete']);
                $router->post('/{id}/toggle', [UiFeatureFlagsController::class, 'toggle']);
            });

            // ── Queue / Jobs ──
            $router->group([
                'prefix' => '/queue',
                'description' => 'Job queue management',
            ], function (Router $router) {
                $router->get('/stats', [UiQueueController::class, 'stats']);
                $router->get('/failed', [UiQueueController::class, 'failed']);
                $router->post('/failed/{id}/retry', [UiQueueController::class, 'retry']);
                $router->delete('/failed/{id}', [UiQueueController::class, 'deleteFailed']);
                $router->post('/flush', [UiQueueController::class, 'flush']);
            });

            // ── Scheduler ──
            $router->get('/scheduler/tasks', [UiSchedulerController::class, 'tasks']);

            // ── Events ──
            $router->get('/events/listeners', [UiEventsController::class, 'listeners']);

            // ── Cache ──
            $router->group([
                'prefix' => '/cache',
                'description' => 'Cache management',
            ], function (Router $router) {
                $router->get('/stats', [UiCacheController::class, 'stats']);
                $router->post('/flush', [UiCacheController::class, 'flush']);
                $router->post('/flush-routes', [UiCacheController::class, 'flushRoutes']);
            });

            // ── Profiler ──
            $router->group([
                'prefix' => '/profiler',
                'description' => 'Request profiler',
            ], function (Router $router) {
                $router->get('/requests', [UiProfilerController::class, 'requests']);
                $router->get('/requests/{id}', [UiProfilerController::class, 'show']);
                $router->post('/clear', [UiProfilerController::class, 'clear']);
            });

            // ── Storage ──
            $router->group([
                'prefix' => '/storage',
                'description' => 'File storage management',
            ], function (Router $router) {
                $router->get('/info', [UiStorageController::class, 'info']);
                $router->get('/files', [UiStorageController::class, 'files']);
                $router->post('/upload', [UiStorageController::class, 'upload']);
                $router->delete('/files', [UiStorageController::class, 'delete']);
            });

            // ── Webhooks ──
            $router->group([
                'prefix' => '/webhooks',
                'description' => 'Webhook management',
            ], function (Router $router) {
                $router->get('', [UiWebhooksController::class, 'list']);
                $router->get('/stats', [UiWebhooksController::class, 'stats']);
                $router->post('', [UiWebhooksController::class, 'create']);
                $router->put('/{id}', [UiWebhooksController::class, 'update']);
                $router->delete('/{id}', [UiWebhooksController::class, 'delete']);
                $router->post('/{id}/toggle', [UiWebhooksController::class, 'toggle']);
                $router->get('/{id}/deliveries', [UiWebhooksController::class, 'deliveries']);
                $router->post('/deliveries/{id}/retry', [UiWebhooksController::class, 'retryDelivery']);
            });

            // ── Notifications ──
            $router->group([
                'prefix' => '/notifications',
                'description' => 'Notification management',
            ], function (Router $router) {
                $router->get('', [UiNotificationsController::class, 'list']);
                $router->get('/stats', [UiNotificationsController::class, 'stats']);
                $router->get('/channels', [UiNotificationsController::class, 'channels']);
            });

            // ── Email Templates ──
            $router->group([
                'prefix' => '/email-templates',
                'description' => 'Email template management',
            ], function (Router $router) {
                $router->get('', [UiEmailTemplatesController::class, 'list']);
                $router->get('/{id}', [UiEmailTemplatesController::class, 'show']);
                $router->post('', [UiEmailTemplatesController::class, 'create']);
                $router->put('/{id}', [UiEmailTemplatesController::class, 'update']);
                $router->delete('/{id}', [UiEmailTemplatesController::class, 'delete']);
                $router->post('/{id}/preview', [UiEmailTemplatesController::class, 'preview']);
            });

            // ── NF525 — French Tax Compliance ──
            $router->group([
                'prefix' => '/nf525',
                'description' => 'NF 525 compliance',
            ], function (Router $router) {
                $router->get('/stats', [UiNf525Controller::class, 'stats']);
                $router->get('/invoices', [UiNf525Controller::class, 'invoices']);
                $router->get('/closings', [UiNf525Controller::class, 'closings']);
                $router->get('/journal', [UiNf525Controller::class, 'journal']);
                $router->post('/verify', [UiNf525Controller::class, 'verify']);
                $router->post('/close', [UiNf525Controller::class, 'close']);
                $router->get('/fec', [UiNf525Controller::class, 'fec']);
            });

            // ── Audit Logs — SOC 2 ──
            $router->group([
                'prefix' => '/audit',
                'description' => 'SOC 2 audit trail',
            ], function (Router $router) {
                $router->get('/logs', [UiAuditController::class, 'logs']);
                $router->get('/logs/{id}', [UiAuditController::class, 'show']);
                $router->get('/stats', [UiAuditController::class, 'stats']);
                $router->post('/purge', [UiAuditController::class, 'purge']);
            });

            // ── Security ──
            $router->group([
                'prefix' => '/security',
                'description' => 'Security monitoring',
            ], function (Router $router) {
                $router->get('/events', [UiSecurityController::class, 'events']);
                $router->get('/lockouts', [UiSecurityController::class, 'lockouts']);
                $router->post('/unlock', [UiSecurityController::class, 'unlock']);
            });

            // ── Worker ──
            $router->group([
                'prefix' => '/worker',
                'description' => 'FrankenPHP worker management',
            ], function (Router $router) {
                $router->get('', [UiWorkerController::class, 'stats']);
                $router->post('/restart', [UiWorkerController::class, 'restart']);
            });

            // ── GDPR / Consent ──
            $router->group([
                'prefix' => '/consent',
                'description' => 'GDPR consent management',
            ], function (Router $router) {
                $router->get('/documents', [UiConsentController::class, 'documents']);
                $router->post('/documents', [UiConsentController::class, 'createDocument']);
                $router->put('/documents/{id}', [UiConsentController::class, 'updateDocument']);
                $router->get('/stats', [UiConsentController::class, 'stats']);
                $router->get('/users/{id}/consents', [UiConsentController::class, 'userConsents']);
            });

            // ── Organizations — Multi-tenant SaaS ──
            $router->group([
                'prefix' => '/organizations',
                'description' => 'Organization management',
            ], function (Router $router) {
                $router->get('', [UiOrganizationsController::class, 'list']);
                $router->get('/stats', [UiOrganizationsController::class, 'stats']);
                $router->get('/{id}', [UiOrganizationsController::class, 'show']);
                $router->get('/{id}/members', [UiOrganizationsController::class, 'members']);
            });

            // ── System ──
            $router->group([
                'prefix' => '/system',
                'description' => 'System information',
            ], function (Router $router) {
                $router->get('/info', [UiSystemController::class, 'info']);
                $router->get('/modules', [UiSystemController::class, 'modules']);
                $router->get('/routes', [UiSystemController::class, 'routes']);
                $router->get('/env', [UiSystemController::class, 'env']);
                $router->get('/database', [UiSystemController::class, 'database']);
                $router->get('/database/tables/{name}', [UiSystemController::class, 'tableInfo']);
                $router->get('/logs', [UiSystemController::class, 'logs']);
            });
        });
    }
}
