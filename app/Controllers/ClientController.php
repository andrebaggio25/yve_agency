<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClientService;
use App\Support\Auth;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clientService) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clients = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), Auth::can('clients.view_all'));
        return $this->view('clients.index', ['clients' => $clients]);
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('clients.create');
        return $this->view('clients.create');
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('clients.create');

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

        return $this->view('clients.show', compact('client', 'contacts', 'marketing', 'financial', 'integrations'));
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientService->findById($clientId, Auth::agencyId());

        if (!$client) {
            return $this->view('errors.404', [], 404);
        }

        return $this->view('clients.edit', ['client' => $client]);
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('clients.edit');

        $clientId = (int) $request->param('clientId');
        $data = $request->only(
            'name', 'legal_name', 'document_type', 'document_number',
            'country', 'state', 'city', 'address', 'postal_code',
            'language', 'timezone', 'currency_code', 'segment', 'niche',
            'status', 'start_date', 'manager_user_id',
        );

        $result = $this->clientService->update($clientId, $data, Auth::agencyId());

        if (!$result['success']) {
            $this->withErrors($result['errors']);
            return $this->redirect("/clientes/{$clientId}/editar");
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
