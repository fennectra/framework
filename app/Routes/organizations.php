<?php

use App\Controllers\OrganizationController;
use App\Middleware\Auth;

// ─── Public : Invitation acceptance ────────────────────────────
$router->get('/organizations/invitations/{token}/accept', [OrganizationController::class, 'acceptInvitation']);

// ─── Authenticated : Organization management ───────────────────
$router->group([
    'prefix' => '/organizations',
    'description' => 'Organization — Multi-org SaaS management',
    'middleware' => [[Auth::class]],
], function ($router) {
    $router->get('', [OrganizationController::class, 'index']);
    $router->post('', [OrganizationController::class, 'store']);
    $router->get('/{id}', [OrganizationController::class, 'show']);
    $router->put('/{id}', [OrganizationController::class, 'update']);
    $router->delete('/{id}', [OrganizationController::class, 'delete']);
    $router->post('/{id}/invite', [OrganizationController::class, 'invite']);
    $router->post('/{id}/members/{memberId}/role', [OrganizationController::class, 'updateMemberRole']);
    $router->delete('/{id}/members/{memberId}', [OrganizationController::class, 'removeMember']);
});