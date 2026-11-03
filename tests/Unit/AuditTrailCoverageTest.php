<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SEC-15: invariante de arquitetura — **ação sensível grava `activity_logs`**.
 *
 * O catálogo abaixo é a lista das ações que precisam deixar rastro. Antes do
 * SEC-15 o financeiro inteiro, as tarefas, as ações de anúncio na Meta, a
 * conexão das contas, o admin da plataforma e a escrita do cliente no Drive
 * mudavam dado sem uma linha de auditoria — quando alguém perguntava "quem
 * apagou essa fatura?", a resposta não existia.
 *
 * O teste é estático de propósito: um teste de feature prova que **uma** rota
 * grava; este prova que **nenhuma das catalogadas deixou de gravar**, e falha
 * no instante em que a chamada sai do método (ou em que o método é renomeado e
 * o catálogo fica mentindo). Ver `tests/Feature/AuditTrailTest.php` para as
 * linhas de verdade no banco.
 */
class AuditTrailCoverageTest extends TestCase
{
    /**
     * Ação sensível → [classe, método, ação gravada].
     *
     * A ação é o primeiro argumento de `ActivityLogger::log`. Quando o método
     * grava mais de uma ação (ligar/desligar), basta uma delas no catálogo.
     *
     * @var list<array{0:class-string,1:string,2:string}>
     */
    private const SENSITIVE = [
        // ── Autenticação ─────────────────────────────────────────────────────
        [\App\Services\AuthService::class, 'attempt',       'login'],
        [\App\Services\AuthService::class, 'logout',        'logout'],
        [\App\Services\AuthService::class, 'resetPassword', 'password_reset'],

        // ── Clientes, perfis e usuários do tenant ────────────────────────────
        [\App\Services\ClientService::class, 'create',           'client_created'],
        [\App\Services\ClientService::class, 'update',           'client_updated'],
        [\App\Services\ClientService::class, 'archive',          'client_archived'],
        [\App\Services\ClientService::class, 'restore',          'client_restored'],
        [\App\Services\ClientService::class, 'grantAccess',      'client_access_granted'],
        [\App\Services\ClientService::class, 'revokeAccess',     'client_access_revoked'],
        [\App\Services\ClientService::class, 'setPortalAccess',  'portal_access_granted'],
        [\App\Services\UserService::class,   'create',           'user_created'],
        [\App\Services\UserService::class,   'update',           'user_updated'],
        [\App\Services\UserService::class,   'delete',           'user_deleted'],
        [\App\Services\RoleService::class,   'create',           'role_created'],
        [\App\Services\RoleService::class,   'update',           'role_updated'],
        [\App\Services\RoleService::class,   'delete',           'role_deleted'],

        // ── Financeiro (SEC-15) ──────────────────────────────────────────────
        [\App\Services\InvoiceService::class,  'create',      'invoice.created'],
        [\App\Services\InvoiceService::class,  'update',      'invoice.updated'],
        [\App\Services\InvoiceService::class,  'delete',      'invoice.deleted'],
        [\App\Services\InvoiceService::class,  'markSent',    'invoice.marked_sent'],
        [\App\Services\InvoiceService::class,  'sendByEmail', 'invoice.sent_by_email'],
        [\App\Services\PaymentService::class,  'record',      'payment.recorded'],
        [\App\Services\PaymentService::class,  'delete',      'payment.deleted'],
        [\App\Services\ContractService::class, 'create',      'contract.created'],
        [\App\Services\ContractService::class, 'update',      'contract.updated'],
        [\App\Services\ContractService::class, 'delete',      'contract.deleted'],

        // ── Tarefas (SEC-15) ─────────────────────────────────────────────────
        [\App\Controllers\TaskController::class, 'store',        'task.created'],
        [\App\Controllers\TaskController::class, 'update',       'task.updated'],
        [\App\Controllers\TaskController::class, 'updateStatus', 'task.status_changed'],
        [\App\Controllers\TaskController::class, 'destroy',      'task.deleted'],

        // ── Configurações da agência (SEC-15) ────────────────────────────────
        [\App\Controllers\SettingsController::class, 'save', 'settings.agency_saved'],

        // ── Tráfego, IA e orgânico (SEC-15) ─────────────────────────────────
        [\App\Controllers\AdsAccountController::class, 'oauthSave', 'ads_account.connected'],
        [\App\Controllers\AdsAccountController::class, 'store',     'ads_account.connected'],
        [\App\Controllers\AdsAccountController::class, 'destroy',   'ads_account.disconnected'],
        [\App\Services\AdsActionService::class,        'createManual', 'ads_action.requested'],
        [\App\Services\AdsActionService::class,        'createFromAi', 'ads_action.requested'],
        [\App\Services\AdsActionService::class,        'approve',      'ads_action.approved'],
        [\App\Services\AdsActionService::class,        'reject',       'ads_action.rejected'],
        [\App\Services\AdsActionService::class,        'execute',      'ads_action.executed'],
        [\App\Controllers\OrganicController::class,    'connect',   'organic_account.connected'],
        [\App\Controllers\OrganicController::class,    'destroy',   'organic_account.disconnected'],
        [\App\Controllers\AiInsightController::class,  'generate',  'ai_insight.generated'],
        [\App\Controllers\AiInsightController::class,  'destroy',   'ai_insight.deleted'],

        // ── Admin da plataforma (SEC-15) ────────────────────────────────────
        [\App\Controllers\Admin\TenantController::class,           'store',       'admin.tenant_created'],
        [\App\Controllers\Admin\TenantController::class,           'update',      'admin.tenant_updated'],
        [\App\Controllers\Admin\TenantController::class,           'destroy',     'admin.tenant_deleted'],
        [\App\Controllers\Admin\PlatformUserController::class,     'store',        'admin.user_created'],
        [\App\Controllers\Admin\PlatformUserController::class,     'update',       'admin.user_updated'],
        [\App\Controllers\Admin\PlatformUserController::class,     'setPassword',  'admin.user_password_set'],
        [\App\Controllers\Admin\PlatformUserController::class,     'sendReset',    'admin.user_reset_sent'],
        [\App\Controllers\Admin\PlatformUserController::class,     'toggleStatus', 'admin.user_status_changed'],
        [\App\Controllers\Admin\GlobalSettingsController::class,   'save',       'admin.global_settings_saved'],
        [\App\Controllers\Admin\SubscriptionPlanController::class, 'storePlan',          'admin.plan_created'],
        [\App\Controllers\Admin\SubscriptionPlanController::class, 'updatePlan',         'admin.plan_updated'],
        [\App\Controllers\Admin\SubscriptionPlanController::class, 'updateSubscription', 'admin.subscription_changed'],
        [\App\Controllers\Admin\MigrationController::class,        'run',        'migrations_run'],
        [\App\Controllers\Admin\MigrationController::class,        'rollback',   'migrations_rollback'],

        // ── Aprovações ───────────────────────────────────────────────────────
        [\App\Services\ContentPlanService::class, 'reopen', 'content_plan.reopened'],

        // ── Portal do cliente: acesso ───────────────────────────────────────
        [\App\Controllers\PortalController::class, 'adminRegenerateToken', 'portal_token_regenerated'],
        [\App\Controllers\PortalController::class, 'adminTogglePortal',    'portal_enabled'],

        // ── Drive: escrita pelo painel ──────────────────────────────────────
        [\App\Controllers\ClientFilesController::class, 'upload',         'drive.file_uploaded'],
        [\App\Controllers\ClientFilesController::class, 'uploadComplete', 'drive.file_uploaded'],
        [\App\Controllers\ClientFilesController::class, 'move',         'drive.items_moved'],
        [\App\Controllers\ClientFilesController::class, 'deleteFile',   'drive.file_deleted'],
        [\App\Controllers\ClientFilesController::class, 'deleteFolder', 'drive.folder_deleted'],
        [\App\Controllers\ClientFilesController::class, 'renameFile',   'drive.file_renamed'],
        [\App\Controllers\ClientFilesController::class, 'renameFolder', 'drive.folder_renamed'],
        [\App\Controllers\ClientFilesController::class, 'restoreFile',  'drive.file_restored'],

        // ── Drive: escrita pelo portal (SEC-15) ─────────────────────────────
        [\App\Controllers\PortalDriveController::class, 'driveUpload',         'drive.file_uploaded'],
        [\App\Controllers\PortalDriveController::class, 'driveUploadComplete', 'drive.file_uploaded'],
        [\App\Controllers\PortalDriveController::class, 'driveMove',           'drive.items_moved'],
        [\App\Controllers\PortalDriveController::class, 'driveDeleteFile',     'drive.file_deleted'],
        [\App\Controllers\PortalDriveController::class, 'driveDeleteFolder',   'drive.folder_deleted'],
        [\App\Controllers\PortalDriveController::class, 'driveRenameFile',     'drive.file_renamed'],
        [\App\Controllers\PortalDriveController::class, 'driveRenameFolder',   'drive.folder_renamed'],
        [\App\Controllers\PortalDriveController::class, 'driveRestoreFile',    'drive.file_restored'],
    ];

    /**
     * Nada que pareça credencial pode ir para o `metadata_json`: a trilha é
     * lida na tela e sai em export. Token de conta de anúncios, senha e chave
     * de API ficam onde já estão (cifrados), não no log.
     *
     * A lista procura o **valor** (variável, índice, chamada), não o nome da
     * ação: `password_reset` e `admin.user_password_set` são rótulos legítimos.
     */
    private const FORBIDDEN_IN_METADATA = [
        '$token', '$password', '$pass', '$secret', '$apiKey', '$apikey',
        '$accessToken', '$pageToken', '$finalToken', '$longToken',
        "['access_token']", "['password']", "['password_hash']",
        "['api_key']", "['apikey']", "['secret']", "['token']",
        '->access_token', '->password', 'password_hash(',
    ];

    public function test_toda_acao_sensivel_grava_na_trilha(): void
    {
        $faltando = [];

        foreach (self::SENSITIVE as [$class, $method, $action]) {
            $body = $this->methodBody($class, $method);
            $file = (string) file_get_contents($this->classFile($class));

            if (!$this->writesTheTrail($class, $body)) {
                $faltando[] = $this->label($class, $method) . ' → não chama ActivityLogger::log';
                continue;
            }

            if (!str_contains($file, "'{$action}'")) {
                $faltando[] = $this->label($class, $method) . " → não grava a ação \"{$action}\"";
            }
        }

        $this->assertSame(
            [],
            $faltando,
            "Ação sensível sem trilha de auditoria (SEC-15 — use App\\Support\\ActivityLogger::log):\n"
            . implode("\n", $faltando)
        );
    }

    /** O catálogo aponta para métodos que existem de verdade. */
    public function test_o_catalogo_nao_mente(): void
    {
        $inexistentes = [];

        foreach (self::SENSITIVE as [$class, $method, ]) {
            if (!class_exists($class) || !method_exists($class, $method)) {
                $inexistentes[] = $this->label($class, $method);
            }
        }

        $this->assertSame(
            [],
            $inexistentes,
            "Catálogo do SEC-15 aponta para método que não existe (renomeou? atualize a lista):\n"
            . implode("\n", $inexistentes)
        );

        // Guarda do próprio teste: catálogo esvaziado passaria calado.
        $this->assertGreaterThan(50, count(self::SENSITIVE));
    }

    /** Nenhuma chamada de log carrega token, senha ou chave de API. */
    public function test_a_trilha_nao_grava_segredo(): void
    {
        $vazamentos = [];

        foreach ($this->appFiles() as $file) {
            foreach ($this->logCalls((string) file_get_contents($file)) as $call) {
                foreach (self::FORBIDDEN_IN_METADATA as $needle) {
                    if (stripos($call, $needle) !== false) {
                        $vazamentos[] = basename($file) . " → ActivityLogger::log com \"{$needle}\"";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $vazamentos,
            "Segredo na trilha de auditoria (o log é lido na tela e vai para export):\n"
            . implode("\n", $vazamentos)
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * O método grava a trilha direto ou por um helper privado da própria classe
     * que grava (`logDrive`, `logExecution`): o que não pode é a ação sair sem
     * nenhum dos dois.
     */
    private function writesTheTrail(string $class, string $body): bool
    {
        if (str_contains($body, 'ActivityLogger::log(')) {
            return true;
        }

        foreach ($this->logHelpers($class) as $helper) {
            if (str_contains($body, '$this->' . $helper . '(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Métodos privados/protegidos da classe cujo corpo grava a trilha.
     *
     * @return list<string>
     */
    private function logHelpers(string $class): array
    {
        $helpers = [];

        foreach ((new \ReflectionClass($class))->getMethods() as $m) {
            if ($m->getDeclaringClass()->getName() !== $class || $m->isPublic()) {
                continue;
            }
            if (str_contains($this->methodBody($class, $m->getName()), 'ActivityLogger::log(')) {
                $helpers[] = $m->getName();
            }
        }

        return $helpers;
    }

    private function methodBody(string $class, string $method): string
    {
        $ref   = new \ReflectionMethod($class, $method);
        $lines = (array) file((string) $ref->getFileName());
        $slice = array_slice($lines, (int) $ref->getStartLine() - 1, (int) $ref->getEndLine() - (int) $ref->getStartLine() + 1);

        return implode('', $slice);
    }

    private function classFile(string $class): string
    {
        return (string) (new \ReflectionClass($class))->getFileName();
    }

    private function label(string $class, string $method): string
    {
        $short = substr((string) strrchr($class, '\\'), 1);

        return "{$short}::{$method}";
    }

    /**
     * Extrai o texto de cada chamada a `ActivityLogger::log(...)`, contando
     * parênteses — é o argumento que importa, não o arquivo inteiro (o nome da
     * ação `portal_token_regenerated` tem "token" e é legítimo).
     *
     * @return list<string>
     */
    private function logCalls(string $code): array
    {
        $calls  = [];
        $needle = 'ActivityLogger::log(';
        $offset = 0;

        while (($pos = strpos($code, $needle, $offset)) !== false) {
            $i     = $pos + strlen($needle);
            $depth = 1;
            $len   = strlen($code);

            while ($i < $len && $depth > 0) {
                if ($code[$i] === '(') {
                    $depth++;
                } elseif ($code[$i] === ')') {
                    $depth--;
                }
                $i++;
            }

            $calls[] = substr($code, $pos, $i - $pos);
            $offset  = $i;
        }

        return $calls;
    }

    /** @return list<string> */
    private function appFiles(): array
    {
        $dir   = dirname(__DIR__, 2) . '/app';
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }

        return $files;
    }
}
