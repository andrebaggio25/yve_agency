<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PaymentRepository;
use App\Repositories\InvoiceRepository;
use App\Support\Auth;

class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepo,
        private InvoiceRepository $invoiceRepo
    ) {}

    public function listByAgency(array $filters = []): array
    {
        return $this->paymentRepo->listByAgency(Auth::agencyId(), $filters);
    }

    public function listByInvoice(int $invoiceId): array
    {
        return $this->paymentRepo->listByAgency(Auth::agencyId(), ['invoice_id' => $invoiceId]);
    }

    public function record(int $invoiceId, array $input): int
    {
        $agencyId = Auth::agencyId();
        $invoice  = $this->invoiceRepo->findWithDetails($invoiceId, $agencyId);
        if (!$invoice) {
            throw new \RuntimeException("Fatura não encontrada.");
        }

        $id = $this->paymentRepo->create([
            'agency_id'      => $agencyId,
            'invoice_id'     => $invoiceId,
            'amount'         => (float) str_replace(',', '.', $input['amount'] ?? '0'),
            'payment_method' => $input['payment_method'] ?? 'other',
            'payment_date'   => $input['payment_date'] ?? date('Y-m-d'),
            'reference'      => trim($input['reference'] ?? '') ?: null,
            'notes'          => trim($input['notes'] ?? '') ?: null,
            'created_by'     => Auth::id(),
        ]);

        $this->invoiceRepo->updateAmountPaid($invoiceId);
        $this->refreshInvoiceStatus($invoiceId, $agencyId, $invoice);

        return $id;
    }

    public function delete(int $id): void
    {
        $payment = $this->paymentRepo->findByIdAndAgency($id, Auth::agencyId());
        if (!$payment) {
            throw new \RuntimeException("Pagamento não encontrado.");
        }
        $this->paymentRepo->deleteById($id, Auth::agencyId());
        $this->invoiceRepo->updateAmountPaid((int)$payment['invoice_id']);
        $invoice = $this->invoiceRepo->findWithDetails((int)$payment['invoice_id'], Auth::agencyId());
        if ($invoice) {
            $this->refreshInvoiceStatus((int)$payment['invoice_id'], Auth::agencyId(), $invoice);
        }
    }

    public function summaryByMonth(string $year): array
    {
        return $this->paymentRepo->summaryByMonth(Auth::agencyId(), $year);
    }

    private function refreshInvoiceStatus(int $invoiceId, int $agencyId, array $invoice): void
    {
        $paid  = $this->paymentRepo->totalByInvoice($invoiceId);
        $total = (float) $invoice['total'];

        if ($total <= 0) return;

        if ($paid >= $total) {
            $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'paid', date('Y-m-d H:i:s'));
        } elseif ($paid > 0) {
            $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'partial');
        } else {
            if (in_array($invoice['status'], ['paid', 'partial'], true)) {
                $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'sent');
            }
        }
    }
}
