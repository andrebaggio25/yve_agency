<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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
        return $this->view('pagamentos.create', compact('invoice'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('payments.create');

        $invoiceId = (int) ($request->input('invoice_id') ?? 0);
        try {
            $this->paymentService->record($invoiceId, $request->all());
            $this->withSuccess('Pagamento registrado.');
            return $this->redirect('/faturas/' . $invoiceId);
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
            return $this->redirect('/pagamentos/novo?invoice_id=' . $invoiceId);
        }
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('payments.delete');

        $id      = (int) $request->param('id');
        $referer = $_SERVER['HTTP_REFERER'] ?? '/pagamentos';
        try {
            $this->paymentService->delete($id);
            $this->withSuccess('Pagamento removido.');
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
        }
        return $this->redirect($referer);
    }
}
