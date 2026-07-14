<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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

    public function index(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $this->invoiceService->markOverdueAll();

        $filters   = $request->only('q', 'status', 'client_id');
        $page      = max(1, (int) $request->query('page', '1'));
        $paginated = $this->invoiceService->listPaginated($filters, $page, 25);
        $clients   = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);

        return $this->view('faturas.index', compact('paginated', 'clients', 'filters'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('invoices.create');

        $clients = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);
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
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
            return $this->redirect('/faturas/nova');
        }
    }

    public function show(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $invoice = $this->invoiceService->findWithItems((int) $request->param('id'));
        return $this->view('faturas.show', compact('invoice'));
    }

    public function edit(Request $request): Response
    {
        Auth::requirePermission('invoices.edit');

        $invoice  = $this->invoiceService->findWithItems((int) $request->param('id'));
        $clients  = $this->clientService->listForUser(Auth::id(), Auth::agencyId(), true);
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
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
            return $this->redirect('/faturas/' . $id . '/editar');
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('invoices.delete');

        try {
            $this->invoiceService->delete((int) $request->param('id'));
            $this->withSuccess('Fatura removida.');
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
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
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
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

        $clientId  = (int) $request->param('clientId');
        $contracts = $this->contractService->activeForClient($clientId);
        return $this->json($contracts);
    }
}
