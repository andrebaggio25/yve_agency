<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClientService;
use App\Services\BillingService;
use App\Services\AutomationService;
use App\Services\GoogleDriveApiService;
use App\Support\Auth;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService        $clientService,
        private readonly BillingService       $billing,
        private readonly AutomationService    $automations,
        private readonly GoogleDriveApiService $driveApi,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $page    = max(1, (int) $request->query('page', '1'));
        $q       = trim((string) $request->query('q', ''));
        $status  = (string) $request->query('status', 'active');
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $agencyId = (int) Auth::agencyId();

        if (Auth::can('clients.view_all')) {
            $paginated = $this->clientService->listPaginated($agencyId, $page, 20, $q, $status);
        } else {
            $all = $this->clientService->listForUser(Auth::id(), $agencyId, false);
            if ($status === 'active') {
                $all = array_values(array_filter($all, fn($c) => ($c['status'] ?? '') === 'active'));
            } elseif ($status === 'inactive') {
                $all = array_values(array_filter($all, fn($c) => ($c['status'] ?? '') !== 'active'));
            }
            $total     = count($all);
            $paginated = ['items' => $all, 'total' => $total, 'page' => 1, 'per_page' => $total, 'pages' => 1];
        }

        return $this->view('clients.index', ['paginated' => $paginated, 'q' => $q, 'status' => $status]);
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('clients.create');
        return $this->view('clients.create');
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('clients.create');

        if (!$this->billing->checkLimit((int) Auth::agencyId(), 'clients')) {
            $this->withError('Limite de clientes do seu plano atingido. Faça upgrade para adicionar mais.');
            return $this->redirect('/clientes/novo');
        }

        $data = $request->only(
            'name', 'legal_name', 'document_type', 'document_number',
            'country', 'state', 'city', 'address', 'postal_code',
            'language', 'timezone', 'currency_code', 'segment', 'niche',
            'start_date', 'manager_user_id',
        );

        $result = $this->clientService->create($data, Auth::agencyId(), Auth::id());

        if (!$result['success']) {
            $this->withErrors($result['errors'])->withInput($data);
            return $this->redirect('/clientes/novo');
        }

        $this->withSuccess('Cliente cadastrado com sucesso.');
        return $this->redirect('/clientes/' . $result['id']);
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientService->findById($clientId, Auth::agencyId());

        if (!$client) {
            return $this->view('errors.404', [], 404);
        }

        $contacts   = $this->clientService->getContacts($clientId);
        $marketing  = $this->clientService->getMarketingProfile($clientId);
        $financial  = $this->clientService->getFinancialProfile($clientId);
        $integrations = $this->clientService->getIntegrations($clientId);

        $driveConnected = $this->driveApi->isConnected((int) Auth::agencyId());
        $driveFolderOk  = false;
        if ($driveConnected && !empty($client['drive_folder_id'])) {
            try {
                $driveFolderOk = $this->driveApi->exists((int) Auth::agencyId(), (string) $client['drive_folder_id']);
            } catch (\Throwable) {
                $driveFolderOk = false;
            }
        }

        return $this->view('clients.show', compact('client', 'contacts', 'marketing', 'financial', 'integrations', 'driveConnected', 'driveFolderOk'));
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientService->findById($clientId, Auth::agencyId());

        if (!$client) {
            return $this->view('errors.404', [], 404);
        }

        return $this->view('clients.edit', [
            'client'            => $client,
            'clientAutomations' => $this->automations->clientAutomations(),
            'clientAutoSettings'=> $this->automations->settingsForClient($clientId),
        ]);
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $agencyId = (int) Auth::agencyId();

        $data = $request->only(
            'name', 'legal_name', 'document_type', 'document_number',
            'country', 'state', 'city', 'address', 'postal_code',
            'language', 'timezone', 'currency_code', 'segment', 'niche',
            'status', 'start_date', 'manager_user_id', 'whatsapp', 'logo_url',
        );
        $data['logo_url'] = trim((string) ($data['logo_url'] ?? '')) ?: null;
        // Booleanos vêm como 'true'/'false' (Postgres faz o cast; PHP false → '' quebraria).
        $data['notify_whatsapp'] = $request->post('notify_whatsapp') ? 'true' : 'false';
        $data['notify_email']    = $request->post('notify_email') ? 'true' : 'false';

        $result = $this->clientService->update($clientId, $data, $agencyId);

        if (!$result['success']) {
            $this->withErrors($result['errors']);
            return $this->redirect("/clientes/{$clientId}/editar");
        }

        // Portal do cliente
        $enablePortal = (bool) $request->post('enable_portal');
        $this->clientService->setPortalAccess($clientId, $agencyId, $enablePortal);

        // Opt-in das automações por cliente
        $autos = $request->post('automations', []);
        $autos = is_array($autos) ? $autos : [];
        foreach (array_keys($this->automations->clientAutomations()) as $key) {
            $this->automations->setClientSetting($agencyId, $clientId, $key, !empty($autos[$key]));
        }

        $this->withSuccess('Cliente atualizado.');
        return $this->redirect("/clientes/{$clientId}");
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('clients.delete');

        $clientId = (int) $request->param('clientId');
        $this->clientService->delete($clientId, Auth::agencyId());

        $this->withSuccess('Cliente removido.');
        return $this->redirect('/clientes');
    }

    /** Cria (ou recria/re-vincula) a pasta do cliente no Google Drive. */
    public function createDriveFolder(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $agencyId = (int) Auth::agencyId();
        $client   = $this->clientService->findById($clientId, $agencyId);
        if (!$client) {
            return $this->view('errors.404', [], 404);
        }

        if (!$this->driveApi->isConnected($agencyId)) {
            $this->withError('Conecte o Google Drive primeiro (em Integrações).');
            return $this->redirect("/clientes/{$clientId}");
        }

        try {
            // "Recriar": limpa o vínculo e deixa o ensureClientFolder re-vincular ou criar.
            if ($request->post('force') && !empty($client['drive_folder_id'])) {
                $this->clientService->clearDriveFolder($clientId, $agencyId);
                $client['drive_folder_id'] = null;
            }
            $this->driveApi->ensureClientFolder($client, $agencyId);
            $this->withSuccess('Pasta do cliente pronta no Google Drive.');
        } catch (\Throwable $e) {
            $this->withError('Falha ao criar a pasta: ' . $e->getMessage());
        }

        return $this->redirect("/clientes/{$clientId}");
    }

    public function accessIndex(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $accesses = $this->clientService->listAccess($clientId, Auth::agencyId());
        $users    = $this->clientService->listUsersForAccess(Auth::agencyId());

        return $this->view('clients.access', compact('clientId', 'accesses', 'users'));
    }

    public function grantAccess(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId    = (int) $request->param('clientId');
        $userId      = (int) $request->post('user_id');
        $accessLevel = (string) $request->post('access_level', 'standard');

        $this->clientService->grantAccess($clientId, $userId, $accessLevel, Auth::agencyId());

        $this->withSuccess('Acesso concedido.');
        return $this->redirect("/clientes/{$clientId}/acesso");
    }

    public function revokeAccess(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $userId   = (int) $request->param('userId');

        $this->clientService->revokeAccess($clientId, $userId, Auth::agencyId());

        $this->withSuccess('Acesso removido.');
        return $this->redirect("/clientes/{$clientId}/acesso");
    }
}
