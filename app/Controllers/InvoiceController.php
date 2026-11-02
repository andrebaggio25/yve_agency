<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\InvoiceService;
use App\Services\PdfService;
use App\Services\ContractService;
use App\Services\ClientService;
use App\Support\Auth;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService  $invoiceService,
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

    public function index(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $this->invoiceService->markOverdueAll();

        $filters   = $request->only('q', 'status', 'client_id');
        $page      = max(1, (int) $request->query('page', '1'));
        $paginated = $this->invoiceService->listPaginated($filters, $page, 25);
        $clients   = $this->selectableClients();

        return $this->view('faturas.index', compact('paginated', 'clients', 'filters'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('invoices.create');

        $clients = $this->selectableClients();
        return $this->view('faturas.create', compact('clients'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('invoices.create');

        $input = $request->all();
        $items = $input['items'] ?? [];
        unset($input['items']);

        try {
            $id = $this->invoiceService->create($input, $items);
            $this->withSuccess('Fatura criada com sucesso.');
            return $this->redirect('/faturas/' . $id);
        } catch (HttpException $e) {
            throw $e;                       // 403/404 do guard: quem responde é o Router
        } catch (\Throwable $e) {
            $this->withFailure($e, 'faturas:store', 'finance.invoice_save_failed');
            $this->withInput($input);
            return $this->redirect('/faturas/nova');
        }
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $invoice = $this->invoiceService->findWithItems((int) $request->param('id'));
        return $this->view('faturas.show', compact('invoice'));
    }

    /**
     * O seletor precisa conter o cliente do registro aberto, mesmo quando ele
     * não aparece na lista (cliente arquivado não entra em seletor). Sem a
     * opção dele nenhuma fica `selected`, o navegador marca a primeira e o
     * POST reatribui a fatura a outro cliente, calado.
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

    public function edit(Request $request): Response
    {
        Auth::requirePermission('invoices.edit');

        $invoice  = $this->invoiceService->findWithItems((int) $request->param('id'));
        $clients  = $this->withCurrentClient($this->selectableClients(), (int) $invoice['client_id']);
        $contracts = !empty($invoice['client_id'])
            ? $this->contractService->activeForClient((int) $invoice['client_id'])
            : [];

        return $this->view('faturas.edit', compact('invoice', 'clients', 'contracts'));
    }

    public function update(Request $request): Response
    {
        Auth::requirePermission('invoices.edit');

        $id    = (int) $request->param('id');
        $input = $request->all();
        $items = $input['items'] ?? [];
        unset($input['items']);

        try {
            $this->invoiceService->update($id, $input, $items);
            $this->withSuccess('Fatura atualizada.');
            return $this->redirect('/faturas/' . $id);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'faturas:update', 'finance.invoice_save_failed');
            return $this->redirect('/faturas/' . $id . '/editar');
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('invoices.delete');

        try {
            $this->invoiceService->delete((int) $request->param('id'));
            $this->withSuccess('Fatura removida.');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'faturas:destroy', 'finance.invoice_delete_failed');
        }
        return $this->redirect('/faturas');
    }

    public function send(Request $request): Response
    {
        Auth::requirePermission('invoices.send');

        $id = (int) $request->param('id');
        try {
            $this->invoiceService->markSent($id);
            $this->withSuccess('Fatura marcada como enviada.');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'faturas:send', 'finance.invoice_save_failed');
        }
        return $this->redirect('/faturas/' . $id);
    }

    /**
     * PDF de verdade (UX-04). Antes isto abria uma tela de impressão e o
     * usuário tinha de imprimir→salvar como PDF na mão — e não dava para
     * anexar no e-mail, que é como cobrança circula.
     */
    public function printView(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $invoice = $this->invoiceService->findWithItems((int) $request->param('id'));
        if (!$invoice) {
            return Response::view('errors.404', [], 404);
        }

        $pdf = $this->pdf->fromView('faturas.print', compact('invoice'));

        return Response::file(
            $pdf,
            $this->pdf->filename('fatura', (string) $invoice['invoice_number'], (string) ($invoice['client_name'] ?? ''))
        );
    }

    public function sendEmail(Request $request): Response
    {
        Auth::requirePermission('invoices.send');

        $id    = (int) $request->param('id');
        $email = trim($request->input('email') ?? '');
        $name  = trim($request->input('name')  ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->withError('E-mail inválido.');
            return $this->redirect('/faturas/' . $id);
        }

        $result = $this->invoiceService->sendByEmail($id, $email, $name ?: $email);

        if ($result['success']) {
            $this->withSuccess('Fatura enviada por e-mail com sucesso.');
        } else {
            $this->withError('Erro ao enviar e-mail: ' . ($result['error'] ?? ''));
        }

        return $this->redirect('/faturas/' . $id);
    }

    /** AJAX: retorna contratos ativos de um cliente */
    public function contractsForClient(Request $request): Response
    {
        Auth::requirePermission('invoices.create');

        $clientId = (int) $request->param('clientId');
        // Defesa em profundidade: a rota já passa pelo ClientAccessMiddleware,
        // mas o endpoint devolve dado de cliente — guarda explícita (SEC-11).
        Auth::requireClientAccess($clientId);

        $contracts = $this->contractService->activeForClient($clientId);
        return $this->json($contracts);
    }
}
