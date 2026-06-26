<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GoogleDriveIntegrationRepository;
use App\Services\GoogleDriveApiService;
use App\Support\Auth;

class GoogleDriveController extends Controller
{
    public function __construct(
        private readonly GoogleDriveIntegrationRepository $integrationRepo,
        private readonly GoogleDriveApiService            $drive,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $integration = $this->integrationRepo->findByAgency((int) Auth::agencyId());
        $configured  = (bool) env('GOOGLE_CLIENT_ID', '') && (bool) env('GOOGLE_CLIENT_SECRET', '');

        return $this->view('integrations.google_drive', compact('integration', 'configured'));
    }

    public function oauthStart(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        if (!env('GOOGLE_CLIENT_ID', '') || !env('GOOGLE_CLIENT_SECRET', '')) {
            $this->withError('Credenciais do Google não configuradas no servidor (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).');
            return $this->redirect('/integrations/google-drive');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['gdrive_oauth_state'] = $state;

        return Response::redirect($this->drive->authUrl($state));
    }

    public function oauthCallback(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $code  = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        $error = (string) $request->query('error', '');

        if ($error !== '') {
            $this->withError('Autorização cancelada no Google.');
            return $this->redirect('/integrations/google-drive');
        }

        if ($code === '' || $state === '' || $state !== ($_SESSION['gdrive_oauth_state'] ?? '')) {
            $this->withError('Requisição OAuth inválida. Tente novamente.');
            return $this->redirect('/integrations/google-drive');
        }

        unset($_SESSION['gdrive_oauth_state']);

        try {
            $result = $this->drive->exchangeCode((int) Auth::agencyId(), $code);
            $email  = $result['email'] ? " ({$result['email']})" : '';
            if (!empty($result['account_changed'])) {
                $this->withError(
                    'Conectado com uma conta diferente' . $email . '. As pastas da conta anterior ('
                    . ($result['previous_email'] ?? '—') . ') ficaram inacessíveis; o sistema passará a usar a nova conta.'
                );
            } elseif (!empty($result['reused'])) {
                $this->withSuccess('Google Drive reconectado' . $email . ' — pasta existente reaproveitada, nada recriado.');
            } else {
                $this->withSuccess('Google Drive conectado' . $email . '! Pasta raiz criada.');
            }
        } catch (\Throwable $e) {
            $this->withError('Falha ao conectar: ' . $e->getMessage());
        }

        return $this->redirect('/integrations/google-drive');
    }

    public function disconnect(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $this->integrationRepo->deactivate((int) Auth::agencyId());
        $this->withSuccess('Google Drive desconectado.');
        return $this->redirect('/integrations/google-drive');
    }
}
