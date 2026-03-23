<?php

use App\Controllers\ConsentController;
use App\Middleware\Auth;

// ─── Public : consultation des documents legaux ───────────────
$router->group([
    'prefix' => '/consent',
    'description' => 'RGPD — Documents legaux (public)',
], function ($router) {
    $router->get('/documents/{key}/latest', [ConsentController::class, 'latest']);
});

// ─── Utilisateur authentifie : donner/consulter son consentement ─
$router->group([
    'prefix' => '/consent',
    'description' => 'RGPD — Consentement utilisateur',
    'middleware' => [[Auth::class, ['user', 'cip', 'manager', 'admin', 'freelance', 'france_travail', 'editor']]],
], function ($router) {
    $router->post('/me', [ConsentController::class, 'consent']);
    $router->get('/me', [ConsentController::class, 'myConsents']);
    $router->delete('/me', [ConsentController::class, 'withdrawMyConsents']);
});

// ─── Admin : CRUD documents legaux ────────────────────────────
$router->group([
    'prefix' => '/consent/documents',
    'description' => 'RGPD — Gestion des documents legaux (admin)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('', [ConsentController::class, 'index']);
    $router->get('/{id}', [ConsentController::class, 'show']);
    $router->post('', [ConsentController::class, 'store']);
});

// ─── DPO / Admin : statistiques et droits RGPD ───────────────
$router->group([
    'prefix' => '/consent/dpo',
    'description' => 'RGPD — Dashboard DPO, conformite et droits des personnes',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    $router->get('/dashboard', [ConsentController::class, 'dashboard']);
    $router->get('/stats', [ConsentController::class, 'stats']);
    $router->get('/compliance', [ConsentController::class, 'complianceRate']);
    $router->get('/non-compliant', [ConsentController::class, 'nonCompliant']);
    $router->get('/users/{userId}/history', [ConsentController::class, 'userHistory']);
    $router->get('/users/{userId}/export', [ConsentController::class, 'exportUser']);
    $router->delete('/users/{userId}/consents', [ConsentController::class, 'withdrawUser']);
});