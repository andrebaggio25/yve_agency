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

    public function rollback(Request $request): Response
    {
        Auth::requirePlatformAdmin();

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
