<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContractService;
use App\Services\ClientService;
use App\Support\Auth;

class ContractController extends Controller
{
    public function __construct(
        private ContractService $contractService,
        private ClientService   $clientService,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('contracts.view');

        $filters   = $request->only('q', 'status', 'client_id');
        $contracts = $this->contractService->list($filters);
        $clients   = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);

        return $this->view('contratos.index', compact('contracts', 'clients', 'filters'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('contracts.create');

        $clients = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);
        return $this->view('contratos.create', compact('clients'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('contracts.create');

        try {
            $id = $this->contractService->create($request->all());
            $this->withSuccess('Contrato criado com sucesso.');
            return $this->redirect('/contratos/' . $id);
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
            return $this->redirect('/contratos/novo');
        }
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('contracts.view');

        $contract = $this->contractService->findOrFail((int) $request->param('id'));
        return $this->view('contratos.show', compact('contract'));
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('contracts.edit');

        $contract = $this->contractService->findOrFail((int) $request->param('id'));
        $clients  = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);
        return $this->view('contratos.edit', compact('contract', 'clients'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('contracts.edit');

        $id = (int) $request->param('id');
        try {
            $this->contractService->update($id, $request->all());
            $this->withSuccess('Contrato atualizado.');
            return $this->redirect('/contratos/' . $id);
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
            return $this->redirect('/contratos/' . $id . '/editar');
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('contracts.delete');

        try {
            $this->contractService->delete((int) $request->param('id'));
            $this->withSuccess('Contrato removido.');
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
        }
        return $this->redirect('/contratos');
    }

    public function printView(Request $request): Response
    {
        Auth::requirePermission('contracts.view');

        $contract = $this->contractService->findOrFail((int) $request->param('id'));
        return $this->view('contratos.print', compact('contract'));
    }
}
