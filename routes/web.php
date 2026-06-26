<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\RoleController;
use App\Controllers\ClientController;
use App\Controllers\ClientFilesController;
use App\Controllers\ContentPlanController;
use App\Controllers\ApprovalController;
use App\Controllers\SettingsController;
use App\Controllers\AutomationController;
use App\Controllers\QueueController;
use App\Controllers\WhatsAppController;
use App\Controllers\WebhookController;
use App\Controllers\FinancialController;
use App\Controllers\FinancialReportController;
use App\Controllers\ContractController;
use App\Controllers\TrafficController;
use App\Controllers\AdsAccountController;
use App\Controllers\AiInsightController;
use App\Controllers\AdsActionController;
use App\Controllers\OrganicController;
use App\Controllers\TaskController;
use App\Controllers\PortalController;
use App\Controllers\InvoiceController;
use App\Controllers\PaymentController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\TenantController;
use App\Controllers\Admin\PlatformUserController;
use App\Controllers\Admin\GlobalSettingsController;
use App\Controllers\Admin\SubscriptionPlanController;
use App\Controllers\BillingController;
use App\Controllers\ReportController;
use App\Controllers\ClickUpController;
use App\Controllers\GoogleDriveController;
use App\Controllers\ClickUpWebhookController;
use App\Controllers\InternalCommentController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\PlatformAdminMiddleware;
use App\Middlewares\PortalMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\ClientAccessMiddleware;

// ─────────────────────────────────────────────────────────────────────────────
// Auth (público)
// ─────────────────────────────────────────────────────────────────────────────
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'],  [RateLimitMiddleware::class, CsrfMiddleware::class]);
$router->post('/logout',[AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

$router->get('/esqueci-senha',           [AuthController::class, 'showForgotPassword']);
$router->post('/esqueci-senha',          [AuthController::class, 'sendResetLink'], [CsrfMiddleware::class]);
$router->get('/redefinir-senha/{token}', [AuthController::class, 'showResetPassword']);
$router->post('/redefinir-senha',        [AuthController::class, 'resetPassword'],  [CsrfMiddleware::class]);

// English aliases for auth
$router->get('/forgot-password',           [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password',          [AuthController::class, 'sendResetLink'], [CsrfMiddleware::class]);
$router->get('/reset-password/{token}',    [AuthController::class, 'showResetPassword']);
$router->post('/reset-password',           [AuthController::class, 'resetPassword'],  [CsrfMiddleware::class]);

// ─────────────────────────────────────────────────────────────────────────────
// Platform Admin (/admin/*)
// ─────────────────────────────────────────────────────────────────────────────
$router->group([PlatformAdminMiddleware::class], function ($router) {

    $router->get('/admin',                              [AdminDashboardController::class, 'index']);
    $router->get('/admin/tenants',                      [TenantController::class, 'index']);
    $router->get('/admin/tenants/criar',                [TenantController::class, 'create']);
    $router->post('/admin/tenants',                     [TenantController::class, 'store'],   [CsrfMiddleware::class]);
    $router->get('/admin/tenants/{id}/editar',          [TenantController::class, 'edit']);
    $router->put('/admin/tenants/{id}',                 [TenantController::class, 'update'],  [CsrfMiddleware::class]);
    $router->delete('/admin/tenants/{id}',              [TenantController::class, 'destroy'], [CsrfMiddleware::class]);

    $router->get('/admin/usuarios',                          [PlatformUserController::class, 'index']);
    $router->get('/admin/usuarios/novo',                     [PlatformUserController::class, 'create']);
    $router->post('/admin/usuarios',                         [PlatformUserController::class, 'store'],        [CsrfMiddleware::class]);
    $router->get('/admin/usuarios/{id}/editar',              [PlatformUserController::class, 'edit']);
    $router->put('/admin/usuarios/{id}',                     [PlatformUserController::class, 'update'],       [CsrfMiddleware::class]);
    $router->post('/admin/usuarios/{id}/senha',              [PlatformUserController::class, 'setPassword'],  [CsrfMiddleware::class]);
    $router->post('/admin/usuarios/{id}/enviar-reset',       [PlatformUserController::class, 'sendReset'],    [CsrfMiddleware::class]);
    $router->post('/admin/usuarios/{id}/status',             [PlatformUserController::class, 'toggleStatus'], [CsrfMiddleware::class]);

    $router->get('/admin/configuracoes',                [GlobalSettingsController::class, 'index']);
    $router->post('/admin/configuracoes',               [GlobalSettingsController::class, 'save'],         [CsrfMiddleware::class]);
    $router->get('/admin/configuracoes/test-evolution', [GlobalSettingsController::class, 'testEvolution']);

    // ── Planos & Assinaturas (admin) ──────────────────────────────────────────
    $router->get('/admin/planos',                       [SubscriptionPlanController::class, 'plans']);
    $router->get('/admin/planos/novo',                  [SubscriptionPlanController::class, 'createPlan']);
    $router->post('/admin/planos',                      [SubscriptionPlanController::class, 'storePlan'],  [CsrfMiddleware::class]);
    $router->get('/admin/planos/{id}/editar',           [SubscriptionPlanController::class, 'editPlan']);
    $router->put('/admin/planos/{id}',                  [SubscriptionPlanController::class, 'updatePlan'], [CsrfMiddleware::class]);
    $router->get('/admin/assinaturas',                          [SubscriptionPlanController::class, 'subscriptions']);
    $router->get('/admin/assinaturas/{agencyId}/editar',        [SubscriptionPlanController::class, 'editSubscription']);
    $router->post('/admin/assinaturas/{agencyId}',              [SubscriptionPlanController::class, 'updateSubscription'], [CsrfMiddleware::class]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Rotas protegidas (requerem login de tenant)
// ─────────────────────────────────────────────────────────────────────────────
$router->group([AuthMiddleware::class], function ($router) {

    // Dashboard
    $router->get('/',          [DashboardController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'index']);

    // ── English URL aliases ───────────────────────────────────────────────────
    // Users
    $router->get('/users',                [UserController::class, 'index']);
    $router->get('/users/new',            [UserController::class, 'create']);
    $router->post('/users',               [UserController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/users/roles',          [RoleController::class, 'index']);
    $router->get('/users/roles/new',      [RoleController::class, 'create']);
    $router->post('/users/roles',         [RoleController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/users/roles/{id}',     [RoleController::class, 'show']);
    $router->get('/users/roles/{id}/edit',[RoleController::class, 'edit']);
    $router->put('/users/roles/{id}',     [RoleController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/users/roles/{id}',  [RoleController::class, 'destroy'],[CsrfMiddleware::class]);
    $router->get('/users/{id}',           [UserController::class, 'show']);
    $router->get('/users/{id}/edit',      [UserController::class, 'edit']);
    $router->put('/users/{id}',           [UserController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/users/{id}',        [UserController::class, 'destroy'],[CsrfMiddleware::class]);
    // Clients
    $router->get('/clients',                  [ClientController::class, 'index']);
    $router->get('/clients/new',              [ClientController::class, 'create']);
    $router->post('/clients',                 [ClientController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/clients/{clientId}',       [ClientController::class, 'show'],   [ClientAccessMiddleware::class]);
    $router->get('/clients/{clientId}/edit',  [ClientController::class, 'edit'],   [ClientAccessMiddleware::class]);
    $router->put('/clients/{clientId}',       [ClientController::class, 'update'], [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clients/{clientId}',    [ClientController::class, 'destroy'],[CsrfMiddleware::class]);
    $router->get('/clients/{clientId}/access',         [ClientController::class, 'accessIndex'],  [ClientAccessMiddleware::class]);
    $router->post('/clients/{clientId}/access',        [ClientController::class, 'grantAccess'],  [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clients/{clientId}/access/{userId}', [ClientController::class, 'revokeAccess'], [CsrfMiddleware::class]);
    // Content plans
    $router->get('/content',                  [ContentPlanController::class, 'index']);
    $router->get('/content/new',              [ContentPlanController::class, 'create']);
    $router->post('/content',                 [ContentPlanController::class, 'store'],          [CsrfMiddleware::class]);
    $router->get('/content/{planId}',         [ContentPlanController::class, 'show']);
    $router->get('/content/{planId}/edit',    [ContentPlanController::class, 'edit']);
    $router->put('/content/{planId}',         [ContentPlanController::class, 'update'],         [CsrfMiddleware::class]);
    $router->delete('/content/{planId}',      [ContentPlanController::class, 'destroy'],        [CsrfMiddleware::class]);
    $router->post('/content/{planId}/send',   [ContentPlanController::class, 'sendToApproval'], [CsrfMiddleware::class]);
    $router->post('/content/{planId}/items',             [ContentPlanController::class, 'storeItem'],   [CsrfMiddleware::class]);
    $router->put('/content/{planId}/items/{itemId}',     [ContentPlanController::class, 'updateItem'],  [CsrfMiddleware::class]);
    $router->delete('/content/{planId}/items/{itemId}',  [ContentPlanController::class, 'destroyItem'], [CsrfMiddleware::class]);
    $router->post('/content/{planId}/items/reorder',     [ContentPlanController::class, 'reorderItems'],[CsrfMiddleware::class]);
    // Approvals
    $router->get('/approvals',                          [ApprovalController::class, 'index']);
    $router->get('/approvals/{planId}',                 [ApprovalController::class, 'show']);
    $router->post('/approvals/{planId}/approve',        [ApprovalController::class, 'approvePlan'],    [CsrfMiddleware::class]);
    $router->post('/approvals/{planId}/revision',       [ApprovalController::class, 'requestRevision'],[CsrfMiddleware::class]);
    $router->post('/approvals/{planId}/items/{itemId}', [ApprovalController::class, 'feedback'],       [CsrfMiddleware::class]);
    // Financial
    $router->get('/financial',          [FinancialController::class,       'index']);
    $router->get('/financial/reports',  [FinancialReportController::class, 'index']);
    // Contracts
    $router->get('/contracts',               [ContractController::class, 'index']);
    $router->get('/contracts/new',           [ContractController::class, 'create']);
    $router->post('/contracts',              [ContractController::class, 'store'],   [CsrfMiddleware::class]);
    $router->get('/contracts/{id}',          [ContractController::class, 'show']);
    $router->get('/contracts/{id}/pdf',      [ContractController::class, 'printView']);
    $router->get('/contracts/{id}/edit',     [ContractController::class, 'edit']);
    $router->put('/contracts/{id}',          [ContractController::class, 'update'],  [CsrfMiddleware::class]);
    $router->delete('/contracts/{id}',       [ContractController::class, 'destroy'], [CsrfMiddleware::class]);
    // Invoices
    $router->get('/invoices',                [InvoiceController::class, 'index']);
    $router->get('/invoices/new',            [InvoiceController::class, 'create']);
    $router->post('/invoices',               [InvoiceController::class, 'store'],    [CsrfMiddleware::class]);
    $router->get('/invoices/{id}',           [InvoiceController::class, 'show']);
    $router->get('/invoices/{id}/edit',      [InvoiceController::class, 'edit']);
    $router->put('/invoices/{id}',           [InvoiceController::class, 'update'],   [CsrfMiddleware::class]);
    $router->delete('/invoices/{id}',        [InvoiceController::class, 'destroy'],  [CsrfMiddleware::class]);
    $router->post('/invoices/{id}/send',     [InvoiceController::class, 'send'],     [CsrfMiddleware::class]);
    $router->get('/invoices/{id}/pdf',       [InvoiceController::class, 'printView']);
    $router->post('/invoices/{id}/email',    [InvoiceController::class, 'sendEmail'],[CsrfMiddleware::class]);
    // Payments
    $router->get('/payments',                [PaymentController::class, 'index']);
    $router->get('/payments/new',            [PaymentController::class, 'create']);
    $router->post('/payments',               [PaymentController::class, 'store'],    [CsrfMiddleware::class]);
    $router->delete('/payments/{id}',        [PaymentController::class, 'destroy'],  [CsrfMiddleware::class]);
    // Traffic / Ads
    $router->get('/traffic',                             [TrafficController::class,    'index']);
    $router->get('/traffic/campaigns/{id}',              [TrafficController::class,    'campaign']);
    $router->get('/traffic/adsets/{id}',                 [TrafficController::class,    'adSet']);
    $router->get('/traffic/accounts',                    [AdsAccountController::class, 'index']);
    $router->get('/traffic/accounts/oauth',              [AdsAccountController::class, 'oauthStart']);
    $router->get('/traffic/accounts/oauth/callback',     [AdsAccountController::class, 'oauthCallback']);
    $router->post('/traffic/accounts/oauth/save',        [AdsAccountController::class, 'oauthSave'],   [CsrfMiddleware::class]);
    $router->get('/traffic/accounts/new',                [AdsAccountController::class, 'create']);
    $router->post('/traffic/accounts',                   [AdsAccountController::class, 'store'],   [CsrfMiddleware::class]);
    $router->post('/traffic/accounts/{id}/sync',         [AdsAccountController::class, 'syncOne'], [CsrfMiddleware::class]);
    $router->delete('/traffic/accounts/{id}',            [AdsAccountController::class, 'destroy'], [CsrfMiddleware::class]);
    $router->get('/traffic/actions',                     [AdsActionController::class,  'index']);
    $router->get('/traffic/actions/new',                 [AdsActionController::class,  'create']);
    $router->post('/traffic/actions',                    [AdsActionController::class,  'store'],   [CsrfMiddleware::class]);
    $router->get('/traffic/actions/{id}',                [AdsActionController::class,  'show']);
    $router->post('/traffic/actions/{id}/approve',       [AdsActionController::class,  'approve'], [CsrfMiddleware::class]);
    $router->post('/traffic/actions/{id}/reject',        [AdsActionController::class,  'reject'],  [CsrfMiddleware::class]);
    $router->post('/traffic/actions/{id}/execute',       [AdsActionController::class,  'execute'], [CsrfMiddleware::class]);
    $router->get('/traffic/accounts/{accountId}/campaigns', [AdsActionController::class, 'campaignsForAccount']);
    // AI Insights
    $router->get('/ai',                             [AiInsightController::class, 'index']);
    $router->get('/ai/generate',                    [AiInsightController::class, 'generateForm']);
    $router->post('/ai/generate',                   [AiInsightController::class, 'generate'],          [CsrfMiddleware::class]);
    $router->get('/ai/recommendations',             [AiInsightController::class, 'recommendations']);
    $router->post('/ai/recommendations/save',       [AiInsightController::class, 'saveRecommendations'],[CsrfMiddleware::class]);
    $router->get('/ai/{id}',                        [AiInsightController::class, 'show']);
    $router->delete('/ai/{id}',                     [AiInsightController::class, 'destroy'],            [CsrfMiddleware::class]);
    // Organic
    $router->get('/organic',                        [OrganicController::class, 'index']);
    $router->get('/organic/accounts',               [OrganicController::class, 'accounts']);
    $router->get('/organic/connect',                [OrganicController::class, 'connectForm']);
    $router->post('/organic/connect',               [OrganicController::class, 'connect'],  [CsrfMiddleware::class]);
    $router->get('/organic/accounts/{id}',          [OrganicController::class, 'account']);
    $router->post('/organic/accounts/{id}/sync',    [OrganicController::class, 'syncOne'],  [CsrfMiddleware::class]);
    $router->delete('/organic/accounts/{id}',       [OrganicController::class, 'destroy'],  [CsrfMiddleware::class]);
    // Tasks
    $router->get('/tasks',                    [TaskController::class, 'index']);
    $router->get('/tasks/new',                [TaskController::class, 'create']);
    $router->post('/tasks',                   [TaskController::class, 'store'],        [CsrfMiddleware::class]);
    $router->get('/tasks/{id}',               [TaskController::class, 'show']);
    $router->get('/tasks/{id}/edit',          [TaskController::class, 'edit']);
    $router->put('/tasks/{id}',               [TaskController::class, 'update'],       [CsrfMiddleware::class]);
    $router->post('/tasks/{id}/status',       [TaskController::class, 'updateStatus'], [CsrfMiddleware::class]);
    $router->delete('/tasks/{id}',            [TaskController::class, 'destroy'],      [CsrfMiddleware::class]);
    // Automações
    $router->get('/automations',           [AutomationController::class, 'index']);
    $router->get('/automations/clients',   [AutomationController::class, 'matrix']);
    $router->post('/automations/clients',  [AutomationController::class, 'saveMatrix'], [CsrfMiddleware::class]);
    $router->put('/automations/{key}',     [AutomationController::class, 'update'],     [CsrfMiddleware::class]);

    // Settings / WhatsApp
    $router->get('/settings',                          [SettingsController::class, 'index']);
    $router->post('/settings',                         [SettingsController::class, 'save'], [CsrfMiddleware::class]);
    $router->get('/settings/whatsapp',                 [WhatsAppController::class, 'index']);
    $router->post('/settings/whatsapp/activate',       [WhatsAppController::class, 'activate'],         [CsrfMiddleware::class]);
    $router->get('/settings/whatsapp/qr',              [WhatsAppController::class, 'qrCode']);
    $router->get('/settings/whatsapp/status',          [WhatsAppController::class, 'checkStatus']);
    $router->post('/settings/whatsapp/disconnect',     [WhatsAppController::class, 'disconnect'],      [CsrfMiddleware::class]);
    $router->post('/settings/whatsapp/webhook',        [WhatsAppController::class, 'configureWebhook'], [CsrfMiddleware::class]);
    // Subscription
    $router->get('/subscription', [BillingController::class, 'index']);
    // Executive report
    $router->get('/executive-report',                      [ReportController::class, 'index']);
    $router->get('/executive-report/client/{clientId}',    [ReportController::class, 'clientReport']);
    // Notifications
    $router->get('/notifications',               [SettingsController::class, 'notificationsIndex']);
    $router->get('/notifications/count',         [SettingsController::class, 'notificationsCount']);
    $router->post('/notifications/mark-all-read',[SettingsController::class, 'notificationsMarkAllRead'], [CsrfMiddleware::class]);
    $router->post('/notifications/{id}/read',    [SettingsController::class, 'notificationsMarkRead'],    [CsrfMiddleware::class]);
    // ── End English aliases ───────────────────────────────────────────────────

    // ── Usuários ──────────────────────────────────────────────────────────────
    $router->get('/usuarios',             [UserController::class, 'index']);
    $router->get('/usuarios/novo',        [UserController::class, 'create']);
    $router->post('/usuarios',            [UserController::class, 'store'],  [CsrfMiddleware::class]);

    // ── Perfis/Roles (must be before /usuarios/{id} wildcard) ────────────────
    $router->get('/usuarios/perfis',              [RoleController::class, 'index']);
    $router->get('/usuarios/perfis/novo',         [RoleController::class, 'create']);
    $router->post('/usuarios/perfis',             [RoleController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/usuarios/perfis/{id}',         [RoleController::class, 'show']);
    $router->get('/usuarios/perfis/{id}/editar',  [RoleController::class, 'edit']);
    $router->put('/usuarios/perfis/{id}',         [RoleController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/usuarios/perfis/{id}',      [RoleController::class, 'destroy'],[CsrfMiddleware::class]);
    // Legacy prefix
    $router->get('/perfis',             [RoleController::class, 'index']);
    $router->get('/perfis/novo',        [RoleController::class, 'create']);
    $router->post('/perfis',            [RoleController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/perfis/{id}',        [RoleController::class, 'show']);
    $router->get('/perfis/{id}/editar', [RoleController::class, 'edit']);
    $router->put('/perfis/{id}',        [RoleController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/perfis/{id}',     [RoleController::class, 'destroy'],[CsrfMiddleware::class]);

    // User detail (wildcard — must come after all static /usuarios/* routes)
    $router->get('/usuarios/{id}',        [UserController::class, 'show']);
    $router->get('/usuarios/{id}/editar', [UserController::class, 'edit']);
    $router->put('/usuarios/{id}',        [UserController::class, 'update'], [CsrfMiddleware::class]);
    $router->delete('/usuarios/{id}',     [UserController::class, 'destroy'],[CsrfMiddleware::class]);

    // ── Clientes ──────────────────────────────────────────────────────────────
    $router->get('/clientes',               [ClientController::class, 'index']);
    $router->get('/clientes/novo',          [ClientController::class, 'create']);
    $router->post('/clientes',              [ClientController::class, 'store'],  [CsrfMiddleware::class]);
    $router->get('/clientes/{clientId}',    [ClientController::class, 'show'],   [ClientAccessMiddleware::class]);
    $router->get('/clientes/{clientId}/editar', [ClientController::class, 'edit'], [ClientAccessMiddleware::class]);
    $router->put('/clientes/{clientId}',    [ClientController::class, 'update'], [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clientes/{clientId}', [ClientController::class, 'destroy'],[CsrfMiddleware::class]);

    // Conteúdos enviados pelo cliente (Drive) — galeria lado agência
    $router->get('/clientes/{clientId}/conteudos',                  [ClientFilesController::class, 'index'],   [ClientAccessMiddleware::class]);
    $router->get('/clientes/{clientId}/conteudos/folders',          [ClientFilesController::class, 'folders'], [ClientAccessMiddleware::class]);
    $router->get('/clientes/{clientId}/conteudos/file/{fileId}/raw',[ClientFilesController::class, 'raw'],      [ClientAccessMiddleware::class]);

    // Acesso de usuários ao cliente
    $router->get('/clientes/{clientId}/acesso',                  [ClientController::class, 'accessIndex'],  [ClientAccessMiddleware::class]);
    $router->post('/clientes/{clientId}/acesso',                 [ClientController::class, 'grantAccess'],  [CsrfMiddleware::class, ClientAccessMiddleware::class]);
    $router->delete('/clientes/{clientId}/acesso/{userId}',      [ClientController::class, 'revokeAccess'], [CsrfMiddleware::class]);

    // ── Planos de Conteúdo ────────────────────────────────────────────────────
    $router->get('/conteudo',                   [ContentPlanController::class, 'index']);
    $router->get('/conteudo/novo',              [ContentPlanController::class, 'create']);
    $router->get('/conteudo/criar',             [ContentPlanController::class, 'create']);
    $router->post('/conteudo',                  [ContentPlanController::class, 'store'],          [CsrfMiddleware::class]);
    $router->get('/conteudo/{planId}',          [ContentPlanController::class, 'show']);
    $router->get('/conteudo/{planId}/editar',   [ContentPlanController::class, 'edit']);
    $router->put('/conteudo/{planId}',          [ContentPlanController::class, 'update'],         [CsrfMiddleware::class]);
    $router->delete('/conteudo/{planId}',       [ContentPlanController::class, 'destroy'],        [CsrfMiddleware::class]);
    $router->post('/conteudo/{planId}/enviar',  [ContentPlanController::class, 'sendToApproval'], [CsrfMiddleware::class]);

    // Itens do plano
    $router->post('/conteudo/{planId}/items',              [ContentPlanController::class, 'storeItem'],   [CsrfMiddleware::class]);
    $router->put('/conteudo/{planId}/items/{itemId}',      [ContentPlanController::class, 'updateItem'],  [CsrfMiddleware::class]);
    $router->delete('/conteudo/{planId}/items/{itemId}',   [ContentPlanController::class, 'destroyItem'], [CsrfMiddleware::class]);
    $router->post('/conteudo/{planId}/items/reorder',      [ContentPlanController::class, 'reorderItems'],[CsrfMiddleware::class]);

    // ── Aprovações ────────────────────────────────────────────────────────────
    $router->get('/aprovacoes',                          [ApprovalController::class, 'index']);
    $router->get('/aprovacoes/{planId}',                 [ApprovalController::class, 'show']);
    $router->post('/aprovacoes/{planId}/aprovar',        [ApprovalController::class, 'approvePlan'],    [CsrfMiddleware::class]);
    $router->post('/aprovacoes/{planId}/revisao',        [ApprovalController::class, 'requestRevision'],[CsrfMiddleware::class]);
    $router->post('/aprovacoes/{planId}/items/{itemId}', [ApprovalController::class, 'feedback'],       [CsrfMiddleware::class]);

    // ── Financeiro ────────────────────────────────────────────────────────────
    $router->get('/financeiro',             [FinancialController::class,       'index']);
    $router->get('/financeiro/relatorios',  [FinancialReportController::class, 'index']);

    // Contratos
    $router->get('/contratos',                  [ContractController::class, 'index']);
    $router->get('/contratos/novo',             [ContractController::class, 'create']);
    $router->post('/contratos',                 [ContractController::class, 'store'],   [CsrfMiddleware::class]);
    $router->get('/contratos/{id}',             [ContractController::class, 'show']);
    $router->get('/contratos/{id}/pdf',         [ContractController::class, 'printView']);
    $router->get('/contratos/{id}/editar',      [ContractController::class, 'edit']);
    $router->put('/contratos/{id}',             [ContractController::class, 'update'],  [CsrfMiddleware::class]);
    $router->delete('/contratos/{id}',          [ContractController::class, 'destroy'], [CsrfMiddleware::class]);

    // Faturas
    $router->get('/faturas',                    [InvoiceController::class, 'index']);
    $router->get('/faturas/nova',               [InvoiceController::class, 'create']);
    $router->post('/faturas',                   [InvoiceController::class, 'store'],    [CsrfMiddleware::class]);
    $router->get('/faturas/{id}',               [InvoiceController::class, 'show']);
    $router->get('/faturas/{id}/editar',        [InvoiceController::class, 'edit']);
    $router->put('/faturas/{id}',               [InvoiceController::class, 'update'],   [CsrfMiddleware::class]);
    $router->delete('/faturas/{id}',            [InvoiceController::class, 'destroy'],  [CsrfMiddleware::class]);
    $router->post('/faturas/{id}/enviar',       [InvoiceController::class, 'send'],     [CsrfMiddleware::class]);
    $router->get('/faturas/{id}/pdf',           [InvoiceController::class, 'printView']);
    $router->post('/faturas/{id}/email',        [InvoiceController::class, 'sendEmail'],[CsrfMiddleware::class]);

    // Contratos ativos por cliente (AJAX usado no form de fatura)
    $router->get('/clientes/{clientId}/contratos-ativos', [InvoiceController::class, 'contractsForClient']);

    // Pagamentos
    $router->get('/pagamentos',                 [PaymentController::class, 'index']);
    $router->get('/pagamentos/novo',            [PaymentController::class, 'create']);
    $router->post('/pagamentos',                [PaymentController::class, 'store'],    [CsrfMiddleware::class]);
    $router->delete('/pagamentos/{id}',         [PaymentController::class, 'destroy'],  [CsrfMiddleware::class]);

    // ── Tráfego Pago ─────────────────────────────────────────────────────────
    $router->get('/trafego',                              [TrafficController::class,     'index']);
    $router->get('/trafego/campanhas/{id}',               [TrafficController::class,     'campaign']);
    $router->get('/trafego/conjuntos/{id}',               [TrafficController::class,     'adSet']);
    $router->get('/trafego/contas',                       [AdsAccountController::class,  'index']);
    $router->get('/trafego/contas/oauth',                 [AdsAccountController::class,  'oauthStart']);
    $router->get('/trafego/contas/oauth/callback',        [AdsAccountController::class,  'oauthCallback']);
    $router->post('/trafego/contas/oauth/salvar',         [AdsAccountController::class,  'oauthSave'],   [CsrfMiddleware::class]);
    $router->get('/trafego/contas/nova',                  [AdsAccountController::class,  'create']);
    $router->post('/trafego/contas',                      [AdsAccountController::class,  'store'],   [CsrfMiddleware::class]);
    $router->post('/trafego/contas/{id}/sync',            [AdsAccountController::class,  'syncOne'], [CsrfMiddleware::class]);
    $router->delete('/trafego/contas/{id}',               [AdsAccountController::class,  'destroy'], [CsrfMiddleware::class]);

    // ── IA Insights ──────────────────────────────────────────────────────────
    $router->get('/ia',                             [AiInsightController::class, 'index']);
    $router->get('/ia/gerar',                       [AiInsightController::class, 'generateForm']);
    $router->post('/ia/gerar',                      [AiInsightController::class, 'generate'],         [CsrfMiddleware::class]);
    $router->get('/ia/recomendacoes',               [AiInsightController::class, 'recommendations']);
    $router->post('/ia/recomendacoes/salvar',       [AiInsightController::class, 'saveRecommendations'],[CsrfMiddleware::class]);
    $router->get('/ia/{id}',                        [AiInsightController::class, 'show']);
    $router->delete('/ia/{id}',                     [AiInsightController::class, 'destroy'],           [CsrfMiddleware::class]);

    // ── Ações em Campanhas ────────────────────────────────────────────────────
    $router->get('/trafego/acoes',                  [AdsActionController::class, 'index']);
    $router->get('/trafego/acoes/nova',             [AdsActionController::class, 'create']);
    $router->post('/trafego/acoes',                 [AdsActionController::class, 'store'],   [CsrfMiddleware::class]);
    $router->get('/trafego/acoes/{id}',             [AdsActionController::class, 'show']);
    $router->post('/trafego/acoes/{id}/aprovar',    [AdsActionController::class, 'approve'], [CsrfMiddleware::class]);
    $router->post('/trafego/acoes/{id}/rejeitar',   [AdsActionController::class, 'reject'],  [CsrfMiddleware::class]);
    $router->post('/trafego/acoes/{id}/executar',   [AdsActionController::class, 'execute'], [CsrfMiddleware::class]);
    $router->get('/trafego/contas/{accountId}/campanhas', [AdsActionController::class, 'campaignsForAccount']);

    // ── Orgânico ─────────────────────────────────────────────────────────────
    $router->get('/organico',                          [OrganicController::class, 'index']);
    $router->get('/organico/contas',                   [OrganicController::class, 'accounts']);
    $router->get('/organico/conectar',                 [OrganicController::class, 'connectForm']);
    $router->post('/organico/conectar',                [OrganicController::class, 'connect'],  [CsrfMiddleware::class]);
    $router->get('/organico/contas/{id}',              [OrganicController::class, 'account']);
    $router->post('/organico/contas/{id}/sync',        [OrganicController::class, 'syncOne'],  [CsrfMiddleware::class]);
    $router->delete('/organico/contas/{id}',           [OrganicController::class, 'destroy'],  [CsrfMiddleware::class]);

    // ── Portal admin (dentro do painel da agência) ───────────────────────────
    $router->post('/clientes/{clientId}/portal/regenerar', [PortalController::class, 'adminRegenerateToken'], [CsrfMiddleware::class]);
    $router->post('/clientes/{clientId}/portal/toggle',    [PortalController::class, 'adminTogglePortal'],    [CsrfMiddleware::class]);

    // ── Tarefas ──────────────────────────────────────────────────────────────
    $router->get('/tarefas',                    [TaskController::class, 'index']);
    $router->get('/tarefas/nova',               [TaskController::class, 'create']);
    $router->post('/tarefas',                   [TaskController::class, 'store'],        [CsrfMiddleware::class]);
    $router->get('/tarefas/{id}',               [TaskController::class, 'show']);
    $router->get('/tarefas/{id}/editar',        [TaskController::class, 'edit']);
    $router->put('/tarefas/{id}',               [TaskController::class, 'update'],       [CsrfMiddleware::class]);
    $router->post('/tarefas/{id}/status',       [TaskController::class, 'updateStatus'], [CsrfMiddleware::class]);
    $router->delete('/tarefas/{id}',            [TaskController::class, 'destroy'],      [CsrfMiddleware::class]);

    // ── WhatsApp (tenant) ─────────────────────────────────────────────────────
    $router->get('/configuracoes/whatsapp',            [WhatsAppController::class, 'index']);
    $router->post('/configuracoes/whatsapp/ativar',    [WhatsAppController::class, 'activate'],         [CsrfMiddleware::class]);
    $router->get('/configuracoes/whatsapp/qr',         [WhatsAppController::class, 'qrCode']);
    $router->get('/configuracoes/whatsapp/status',     [WhatsAppController::class, 'checkStatus']);
    $router->post('/configuracoes/whatsapp/desconectar',[WhatsAppController::class, 'disconnect'],      [CsrfMiddleware::class]);
    $router->post('/configuracoes/whatsapp/webhook',   [WhatsAppController::class, 'configureWebhook'], [CsrfMiddleware::class]);

    // ── Integração ClickUp ───────────────────────────────────────────────────
    $router->get('/integrations/clickup',  [ClickUpController::class, 'index']);
    $router->post('/integrations/clickup', [ClickUpController::class, 'store'],   [CsrfMiddleware::class]);
    $router->delete('/integrations/clickup', [ClickUpController::class, 'destroy'], [CsrfMiddleware::class]);

    // ── Integração Google Drive (OAuth) ──────────────────────────────────────
    $router->get('/integrations/google-drive',                [GoogleDriveController::class, 'index']);
    $router->get('/integrations/google-drive/oauth/start',    [GoogleDriveController::class, 'oauthStart']);
    $router->get('/integrations/google-drive/oauth/callback', [GoogleDriveController::class, 'oauthCallback']);
    $router->post('/integrations/google-drive/disconnect',    [GoogleDriveController::class, 'disconnect'], [CsrfMiddleware::class]);

    // ── Configurações (agência) ──────────────────────────────────────────────
    $router->get('/configuracoes',  [SettingsController::class, 'index']);
    $router->post('/configuracoes', [SettingsController::class, 'save'], [CsrfMiddleware::class]);

    // ── Assinatura (agência) ─────────────────────────────────────────────────
    $router->get('/assinatura', [BillingController::class, 'index']);

    // ── Relatório Executivo ──────────────────────────────────────────────────
    $router->get('/relatorio-executivo',                   [ReportController::class, 'index']);
    $router->get('/relatorio-executivo/cliente/{clientId}',[ReportController::class, 'clientReport']);

    // ── Notificações (JSON) ───────────────────────────────────────────────────
    $router->get('/notificacoes',                            [SettingsController::class, 'notificationsIndex']);
    $router->get('/notificacoes/count',                      [SettingsController::class, 'notificationsCount']);
    $router->post('/notificacoes/todas-lidas',               [SettingsController::class, 'notificationsMarkAllRead'], [CsrfMiddleware::class]);
    $router->post('/notificacoes/{id}/lida',                 [SettingsController::class, 'notificationsMarkRead'],    [CsrfMiddleware::class]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Público — sem autenticação de sessão
// ─────────────────────────────────────────────────────────────────────────────

// Cron: processar fila de notificações (token via ?token=)
$router->any('/queue/run',          [QueueController::class, 'run']);
// Cron: sincronizar ads (token via ?token=)
$router->any('/queue/sync-ads',     [QueueController::class, 'syncAds']);
// Cron: sincronizar orgânico (token via ?token=)
$router->any('/queue/sync-organic', [QueueController::class, 'syncOrganic']);
// Cron: motor de automação — enfileira regras agendadas e processa a fila de jobs
$router->any('/queue/scheduler',    [QueueController::class, 'scheduler']);
$router->any('/queue/work',         [QueueController::class, 'work']);

// API: comentários internos (equipe) — autenticado por sessão, sem CSRF (JSON API)
$router->group([AuthMiddleware::class], function ($router) {
    $router->get( '/api/comentarios/{type}/{entityId}', [InternalCommentController::class, 'index']);
    $router->post('/api/comentarios/{type}/{entityId}', [InternalCommentController::class, 'store']);
});

// Webhook Evolution API (token único por instância)
$router->post('/webhook/evolution/{token}', [WebhookController::class, 'evolution']);

// Webhook ClickUp (token único por agência, valida HMAC X-Signature)
$router->post('/webhook/clickup/{token}', [ClickUpWebhookController::class, 'handle']);

// ─────────────────────────────────────────────────────────────────────────────
// Portal do Cliente (público — acesso via portal_token na URL)
// ─────────────────────────────────────────────────────────────────────────────
$router->group([PortalMiddleware::class], function ($router) {
    $router->get('/portal/{portal_token}',                              [PortalController::class, 'index']);
    $router->get('/portal/{portal_token}/planos',                      [PortalController::class, 'plans']);
    $router->get('/portal/{portal_token}/planos/{planId}',             [PortalController::class, 'planShow']);
    $router->post('/portal/{portal_token}/planos/{planId}/aprovar',    [PortalController::class, 'planApprove'],  [CsrfMiddleware::class]);
    $router->post('/portal/{portal_token}/planos/{planId}/revisao',    [PortalController::class, 'planRevision'], [CsrfMiddleware::class]);
    $router->post('/portal/{portal_token}/planos/{planId}/items/{itemId}/feedback', [PortalController::class, 'itemFeedback']);
    $router->get('/portal/{portal_token}/faturas',                     [PortalController::class, 'invoices']);
    $router->get('/portal/{portal_token}/contratos',                   [PortalController::class, 'contracts']);

    // Envio de conteúdos (Drive) — JSON, sem CSRF (igual itemFeedback)
    $router->get('/portal/{portal_token}/arquivos',                    [PortalController::class, 'driveFiles']);
    $router->get('/portal/{portal_token}/drive/folders',               [PortalController::class, 'driveFolders']);
    $router->post('/portal/{portal_token}/drive/folders',              [PortalController::class, 'driveCreateFolder']);
    $router->post('/portal/{portal_token}/drive/upload',               [PortalController::class, 'driveUpload']);
    $router->post('/portal/{portal_token}/drive/file/{fileId}/delete', [PortalController::class, 'driveDeleteFile']);
    $router->post('/portal/{portal_token}/drive/file/restore',         [PortalController::class, 'driveRestoreFile']);
    $router->post('/portal/{portal_token}/drive/folder/{folderId}/delete', [PortalController::class, 'driveDeleteFolder']);
    $router->get('/portal/{portal_token}/drive/file/{fileId}/raw',     [PortalController::class, 'driveFileRaw']);
});
