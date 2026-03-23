<?php

use App\Controllers\AuditController;
use App\Middleware\Auth;

// ─── Admin only : Audit Trail (SOC 2 compliance) ──────────────
$router->group([
    'prefix' => '/audit',
    'description' => 'Audit Trail — SOC 2 compliance',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('/stats', [AuditController::class, 'stats']);
    $router->get('/recent', [AuditController::class, 'recentActivity']);
    $router->get('/entity/{type}/{entityId}', [AuditController::class, 'forEntity']);
    $router->get('/users/{userId}', [AuditController::class, 'byUser']);
    $router->get('/{id}', [AuditController::class, 'show']);
    $router->get('', [AuditController::class, 'index']);
});