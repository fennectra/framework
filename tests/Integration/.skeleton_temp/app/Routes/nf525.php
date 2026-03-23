<?php

use App\Controllers\Nf525Controller;
use App\Middleware\Auth;

// ─── NF525 — Conformite fiscale (admin only) ──────────────────
$router->group([
    'prefix' => '/nf525',
    'description' => 'NF525 — Conformite fiscale (factures, clotures, journal)',
    'middleware' => [[Auth::class, ['admin']]],
], function ($router) {
    // Factures
    $router->get('/invoices', [Nf525Controller::class, 'index']);
    $router->get('/invoices/{id}', [Nf525Controller::class, 'show']);
    $router->post('/invoices', [Nf525Controller::class, 'store']);
    $router->post('/invoices/{id}/credit', [Nf525Controller::class, 'creditNote']);

    // Clotures
    $router->get('/closings', [Nf525Controller::class, 'closings']);
    $router->post('/closings', [Nf525Controller::class, 'createClosing']);

    // Verification et export
    $router->get('/verify', [Nf525Controller::class, 'verifyChain']);
    $router->get('/fec/export', [Nf525Controller::class, 'exportFec']);

    // Journal
    $router->get('/journal', [Nf525Controller::class, 'journal']);

    // Statistiques
    $router->get('/stats', [Nf525Controller::class, 'stats']);
});