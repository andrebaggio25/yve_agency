<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\RoleController;
use App\Controllers\ClientController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\ClientAccessMiddleware;

// ─────────────────────────────────────────────────────────────────────────────
// Auth (público)
// ─────────────────────────────────────────────────────────────────────────────
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'],  [RateLimitMiddleware::class, CsrfMiddleware::class]);
$router->post('/logout',[AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

$router->get('/esqueci-senha',      [AuthController::class, 'showForgotPassword']);
$router->post('/esqueci-senha',     [AuthController::class, 'sendResetLink'], [CsrfMiddleware::class]);
$router->get('/redefinir-senha/{token}', [AuthController::class, 'showResetPassword']);
$router->post('/redefinir-senha',   [AuthController::class, 'resetPassword'],  [CsrfMiddleware::class]);

// ─────────────────────────────────────────────────────────────────────────────
// Rotas protegidas (requerem login)
// ─────────────────────────────────────────────────────────────────────────────
$router->group([AuthMiddleware::class], function ($router) {

    // Dashboard
    $router->get('/',          [DashboardController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'index']);

    // ── Usuários ──────────────────────────────────────────────────────────────
    $router->get('/usuarios',               [UserController::class, 'index']);
    $router->get('/usuarios/novo',          [UserController::class, 'create']);
    $router->post('/usuarios',              [UserController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/usuarios/{id}',          [UserController::class, 'show']);
    $router->get('/usuarios/{id}/editar',   [UserController::class, 'edit']);
    $router->put('/usuarios/{id}',          [UserController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/usuarios/{id}',       [UserController::class, 'destroy'],[CsrfMiddleware::class]);

    // ── Perfis/Roles ──────────────────────────────────────────────────────────
    $router->get('/perfis',             [RoleController::class, 'index']);
    $router->get('/perfis/novo',        [RoleController::class, 'create']);
    $router->post('/perfis',            [RoleController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/perfis/{id}',        [RoleController::class, 'show']);
    $router->get('/perfis/{id}/editar', [RoleController::class, 'edit']);
    $router->put('/perfis/{id}',        [RoleController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/perfis/{id}',     [RoleController::class, 'destroy'],[CsrfMiddleware::class]);

    // ── Clientes ──────────────────────────────────────────────────────────────
    $router->get('/clientes',               [ClientController::class, 'index']);
    $router->get('/clientes/novo',          [ClientController::class, 'create']);
    $router->post('/clientes',              [ClientController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/clientes/{clientId}',    [ClientController::class, 'show'],   [ClientAccessMiddleware::class]);
    $router->get('/clientes/{clientId}/editar', [ClientController::class, 'edit'], [ClientAccessMiddleware::class]);
    $router->put('/clientes/{clientId}',    [ClientController::class, 'update'], [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clientes/{clientId}', [ClientController::class, 'destroy'],[CsrfMiddleware::class]);

    // Acesso de usuários ao cliente
    $router->get('/clientes/{clientId}/acesso',    [ClientController::class, 'accessIndex'],  [ClientAccessMiddleware::class]);
    $router->post('/clientes/{clientId}/acesso',   [ClientController::class, 'grantAccess'],  [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clientes/{clientId}/acesso/{userId}', [ClientController::class, 'revokeAccess'], [CsrfMiddleware::class]);
});
