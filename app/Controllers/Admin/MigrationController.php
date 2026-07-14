<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MigrationService;
use App\Support\ActivityLogger;
use App\Support\Auth;

/**
 * Painel de migrations (Platform Admin) — permite ver o estado do schema e rodar
 * as migrations pendentes direto pelo sistema, sem CLI. Conecta no mesmo banco
 * (Supabase) usando o phinx.php do projeto.
 */
class MigrationController extends Controller
{
    public function __construct(private readonly MigrationService $migrations) {}

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $status = $this->migrations->status();
        $log    = flash('migration_log');

        return $this->view('admin.migrations.index', compact('status', 'log'));
    }

    public function run(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $result = $this->migrations->migrate();

        ActivityLogger::log('migrations_run', 'admin', Auth::id(), null, [
            'success' => $result['success'],
        ]);

        flash('migration_log', $result['log'] ?: ($result['error'] ?? ''));
        if ($result['success']) {
            $this->withSuccess('Migrations executadas com sucesso.');
        } else {
            $this->withError('Falha ao executar migrations: ' . ($result['error'] ?? 'erro desconhecido'));
        }

        return $this->redirect('/admin/migrations');
    }

    /**
     * Rollback — a única ação verdadeiramente destrutiva do painel: reverter
     * uma migration pode **apagar colunas e tabelas com dados de clientes**, e
     * não há backup automático.
     *
     * Guarda-corpo (ADM-01): antes só existia um `confirm()` no navegador —
     * frontend, portanto burlável e fácil de clicar sem ler. Agora o servidor
     * exige que o operador **digite** a palavra de confirmação. Não é
     * burocracia: é o intervalo entre o impulso e o irreversível.
     */
    private const ROLLBACK_CONFIRMATION = 'REVERTER';

    public function rollback(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $typed = trim((string) $request->post('confirmation', ''));

        if (!hash_equals(self::ROLLBACK_CONFIRMATION, strtoupper($typed))) {
            ActivityLogger::log('migrations_rollback_blocked', 'admin', Auth::id(), null, [
                'reason' => 'confirmação ausente ou incorreta',
            ]);

            $this->withError(
                'Rollback NÃO executado: digite ' . self::ROLLBACK_CONFIRMATION
                . ' no campo de confirmação. Faça backup do banco antes — a operação pode apagar dados.'
            );
            return $this->redirect('/admin/migrations');
        }

        $result = $this->migrations->rollback();

        ActivityLogger::log('migrations_rollback', 'admin', Auth::id(), null, [
            'success' => $result['success'],
        ]);

        flash('migration_log', $result['log'] ?: ($result['error'] ?? ''));
        if ($result['success']) {
            $this->withSuccess('Rollback executado.');
        } else {
            $this->withError('Falha no rollback: ' . ($result['error'] ?? 'erro desconhecido'));
        }

        return $this->redirect('/admin/migrations');
    }
}
