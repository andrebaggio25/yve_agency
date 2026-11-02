<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContractService;
use App\Services\PdfService;
use App\Services\ClientService;
use App\Support\Auth;

class ContractController extends Controller
{
    public function __construct(
        private ContractService $contractService,
        private ClientService   $clientService,
        private PdfService      $pdf,
    ) {}

    /**
     * Clientes que o usuário pode escolher no formulário e no filtro. A agência
     * toda só com `clients.view_all` (SEC-11): antes o seletor oferecia todo
     * cliente da agência a quem não alcança nenhum — e o POST era aceito.
     *
     * @return array<int,array<string,mixed>>
     */
    private function selectableClients(): array
    {
        return $this->clientService->listForUser(
            (int) Auth::id(),
            (int) Auth::agencyId(),
            Auth::can('clients.view_all') || Auth::isPlatformAdmin(),
        );
    }

    /**
     * O seletor precisa conter o cliente do registro aberto, mesmo quando ele
     * não aparece na lista (cliente arquivado não entra em seletor). Sem a
     * opção dele nenhuma fica `selected` e salvar trocaria o cliente.
     *
     * @param array<int,array<string,mixed>> $clients
     * @return array<int,array<string,mixed>>
     */
    private function withCurrentClient(array $clients, int $clientId): array
    {
        if ($clientId <= 0) {
            return $clients;
        }

        foreach ($clients as $client) {
            if ((int) $client['id'] === $clientId) {
                return $clients;
            }
        }

        $current = $this->clientService->findById($clientId, (int) Auth::agencyId());

        return $current ? [...$clients, $current] : $clients;
    }

    public function index(Request $request): Response
    {
        Auth::requirePermission('contracts.view');

        $filters   = $request->only('q', 'status', 'client_id');
        $contracts = $this->contractService->list($filters);
        $clients   = $this->selectableClients();

        return $this->view('contratos.index', compact('contracts', 'clients', 'filters'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('contracts.create');

        $clients = $this->selectableClients();
        return $this->view('contratos.create', compact('clients'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('contracts.create');

        try {
            $id = $this->contractService->create($request->all());
            $this->withSuccess('Contrato criado com sucesso.');
            return $this->redirect('/contratos/' . $id);
        } catch (HttpException $e) {
            throw $e;                       // 403/404 do guard: quem responde é o Router
        } catch (\Throwable $e) {
            $this->withFailure($e, 'contratos:store', 'finance.contract_save_failed');
            $this->withInput($request->all());
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
        $clients  = $this->withCurrentClient($this->selectableClients(), (int) $contract['client_id']);

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
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'contratos:update', 'finance.contract_save_failed');
            return $this->redirect('/contratos/' . $id . '/editar');
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('contracts.delete');

        try {
            $this->contractService->delete((int) $request->param('id'));
            $this->withSuccess('Contrato removido.');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'contratos:destroy', 'finance.contract_delete_failed');
        }
        return $this->redirect('/contratos');
    }

    /** PDF real do contrato (UX-04) — era tela de impressão. */
    public function printView(Request $request): Response
    {
        Auth::requirePermission('contracts.view');

        $contract = $this->contractService->findOrFail((int) $request->param('id'));

        $pdf = $this->pdf->fromView('contratos.print', compact('contract'));

        return Response::file(
            $pdf,
            $this->pdf->filename('contrato', (string) ($contract['contract_number'] ?? $contract['id']), (string) ($contract['client_name'] ?? ''))
        );
    }
}
