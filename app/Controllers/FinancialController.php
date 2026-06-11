<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContractService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\Auth;

class FinancialController extends Controller
{
    public function __construct(
        private ContractService $contractService,
        private InvoiceService  $invoiceService,
        private PaymentService  $paymentService,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('invoices.view');

        $this->invoiceService->markOverdueAll();

        $year = (string) ($request->query('year') ?? date('Y'));

        return $this->view('financeiro.index', [
            'contractSummary' => $this->contractService->summary(),
            'invoiceSummary'  => $this->invoiceService->summary(),
            'monthlyPayments' => $this->paymentService->summaryByMonth($year),
            'year'            => $year,
        ]);
    }
}
