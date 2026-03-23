<?php

use App\Controllers\EmailTemplateController;
use App\Middleware\Auth;

// ─── Admin only : Email Templates ──────────────────────────────
$router->group([
    'prefix' => '/email-templates',
    'description' => 'Email Templates — Gestion des templates email',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('', [EmailTemplateController::class, 'index']);
    $router->get('/{id}', [EmailTemplateController::class, 'show']);
    $router->post('', [EmailTemplateController::class, 'store']);
    $router->put('/{id}', [EmailTemplateController::class, 'update']);
    $router->delete('/{id}', [EmailTemplateController::class, 'delete']);
});