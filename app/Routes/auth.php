<?php

use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\PermissionController;
use App\Controllers\Auth\RoleController;
use App\Middleware\Auth;

// ─── Public : Authentication ───────────────────────────────────
$router->group([
    'prefix' => '/auth',
    'description' => 'Authentication — Public routes',
], function ($router) {
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/activate/{token}', [AuthController::class, 'activate']);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ─── Authenticated : User routes ───────────────────────────────
$router->group([
    'prefix' => '/auth',
    'description' => 'Authentication — Authenticated routes',
    'middleware' => [[Auth::class, []]],
], function ($router) {
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/me', [AuthController::class, 'me']);
});

// ─── Admin only : Roles management ────────────────────────────
$router->group([
    'prefix' => '/auth/roles',
    'description' => 'Authentication — Roles management (admin)',
    'middleware' => [[Auth::class, ['role:admin']]],
], function ($router) {
    $router->get('', [RoleController::class, 'index']);
    $router->post('', [RoleController::class, 'store']);
    $router->get('/{id}', [RoleController::class, 'show']);
    $router->put('/{id}', [RoleController::class, 'update']);
    $router->delete('/{id}', [RoleController::class, 'delete']);
    $router->post('/{id}/permissions', [RoleController::class, 'assignPermissions']);
});

// ─── Admin only : Permissions management ──────────────────────
$router->group([
    'prefix' => '/auth/permissions',
    'description' => 'Authentication — Permissions management (admin)',
    'middleware' => [[Auth::class, ['role:admin']]],
], function ($router) {
    $router->get('', [PermissionController::class, 'index']);
    $router->post('', [PermissionController::class, 'store']);
    $router->get('/{id}', [PermissionController::class, 'show']);
    $router->put('/{id}', [PermissionController::class, 'update']);
    $router->delete('/{id}', [PermissionController::class, 'delete']);
});