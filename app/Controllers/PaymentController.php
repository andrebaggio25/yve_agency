<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use App\Support\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('payments.view');

        $filters  = $request->only('client_id', 'invoice_id');
        $payments = $this->paymentService->listByAgency($filters);
        return $this->view('pagamentos.index', compact('payments', 'filters'));
    }

    /** Formulário: registrar pagamento para uma fatura específica */
    public function create(Request $request): Response
    {
        Auth::requirePermission('payments.create');

        $invoiceId = (int) $request->query('invoice_id');
        $invoice   = $invoiceId ? $this->invoiceService->findWithItems($invoiceId) : null;
        $this->requireInvoiceClientAccess($invoice);

        return $this->view('pagamentos.create', compact('invoice'));
    }

    /**
     * A fatura é de um cliente: escopo de agência não basta — quem não tem
     * `clients.view_all` só mexe no financeiro dos clientes que alcança (SEC-11).
     *
     * @param array<string,mixed>|null $invoice
     */
    private function requireInvoiceClientAccess(?array $invoice): void
    {
        if ($invoice && !empty($invoice['client_id'])) {
            Auth::requireClientAccess((int) $invoice['client_id']);
        }
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('payments.create');

        $invoiceId = (int) ($request->input('invoice_id') ?? 0);
        $this->requireInvoiceClientAccess($invoiceId ? $this->invoiceService->find($invoiceId) : null);

        try {
            $this->paymentService->record($invoiceId, $request->all());
            $this->withSuccess('Pagamento registrado.');
            return $this->redirect('/faturas/' . $invoiceId);
        } catch (HttpException $e) {
            throw $e;                       // 403/404 do guard: quem responde é o Router
        } catch (\Throwable $e) {
            $this->withFailure($e, 'pagamentos:store', 'finance.payment_save_failed');
            return $this->redirect('/pagamentos/novo?invoice_id=' . $invoiceId);
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('payments.delete');

        // Volta para a fatura do pagamento, nunca para o `Referer` (SEC-16): o
        // cabeçalho vem do navegador e aceitava `Location:` para fora do app.
        $id = (int) $request->param('id');
        try {
            $invoiceId = $this->paymentService->delete($id);
            $this->withSuccess('Pagamento removido.');
            return $this->redirect('/faturas/' . $invoiceId);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->withFailure($e, 'pagamentos:destroy', 'finance.payment_delete_failed');
            return $this->redirect('/pagamentos');
        }
    }
}
