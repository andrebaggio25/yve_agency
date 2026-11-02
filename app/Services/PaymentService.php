<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\PaymentRepository;
use App\Repositories\InvoiceRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;
use App\Support\Money;

class PaymentService
{
    /** Formas de pagamento aceitas (o mesmo catálogo do `<input type="radio">`). */
    private const METHODS = ['pix', 'boleto', 'credit_card', 'bank_transfer', 'cash', 'other'];

    /**
     * Situações de fatura que recebem pagamento — a mesma régua que a view usa
     * para mostrar o botão "Registrar" (`faturas/show.php`). A regra existia só
     * na tela: `POST /pagamentos` aceitava fatura **cancelada** e
     * `refreshInvoiceStatus` gravava `paid` em cima, então a fatura cancelada
     * (que `summaryByAgency` deixa fora do faturado e do recebido) ressuscitava
     * somando no painel. `paid` fica de fora pelo saldo, não por aqui.
     */
    private const PAYABLE_STATUSES = ['sent', 'overdue', 'partial'];

    public function __construct(
        private PaymentRepository $paymentRepo,
        private InvoiceRepository $invoiceRepo
    ) {}

    public function listByAgency(array $filters = []): array
    {
        return $this->paymentRepo->listByAgency(Auth::agencyId(), $this->scopeToReach($filters));
    }

    public function listByInvoice(int $invoiceId): array
    {
        return $this->paymentRepo->listByAgency(
            Auth::agencyId(),
            $this->scopeToReach(['invoice_id' => $invoiceId]),
        );
    }

    /**
     * Recorte por cliente alcançado (SEC-11): o pagamento carrega o nome do
     * cliente na listagem, então vale a mesma régua da fatura.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function scopeToReach(array $filters): array
    {
        $clientIds = Auth::reachableClientIds();
        if ($clientIds !== null) {
            $filters['client_ids'] = $clientIds;
        }

        return $filters;
    }

    public function record(int $invoiceId, array $input): int
    {
        $agencyId = Auth::agencyId();
        $invoice  = $this->invoiceRepo->findWithDetails($invoiceId, $agencyId);
        if (!$invoice) {
            throw new ValidationException(t('finance.invoice_not_found'));
        }

        $this->requirePayable($invoice);
        $amount = $this->amountFor($invoiceId, (float) $invoice['total'], $input['amount'] ?? null);
        $method = $this->method($input['payment_method'] ?? null);

        $id = $this->paymentRepo->create([
            'agency_id'      => $agencyId,
            'invoice_id'     => $invoiceId,
            'amount'         => $amount,
            'payment_method' => $method,
            'payment_date'   => trim((string) ($input['payment_date'] ?? '')) ?: date('Y-m-d'),
            'reference'      => trim($input['reference'] ?? '') ?: null,
            'notes'          => trim($input['notes'] ?? '') ?: null,
            'created_by'     => Auth::id(),
        ]);

        $this->invoiceRepo->updateAmountPaid($invoiceId);
        $this->refreshInvoiceStatus($invoiceId, $agencyId, $invoice);

        ActivityLogger::log('payment.recorded', 'finance', null, (int) $invoice['client_id'], [
            'payment_id'     => $id,
            'invoice_id'     => $invoiceId,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'amount'         => $amount,
            'payment_method' => $method,
        ]);

        return $id;
    }

    /**
     * A fatura está em situação de receber pagamento? (SEC-16) Mensagem por
     * situação: cancelada explica que é cancelada, rascunho pede que a fatura
     * seja enviada antes, e `paid` cai na de sempre — não tem saldo.
     *
     * @param array<string,mixed> $invoice
     */
    private function requirePayable(array $invoice): void
    {
        $status = (string) ($invoice['status'] ?? '');
        if (in_array($status, self::PAYABLE_STATUSES, true)) {
            return;
        }

        throw new ValidationException(t(match ($status) {
            'cancelled' => 'finance.payment_invoice_cancelled',
            'paid'      => 'finance.payment_no_balance',
            default     => 'finance.payment_invoice_not_sent',
        }));
    }

    /**
     * Valor do pagamento (SEC-16). Antes era `(float) str_replace(',', '.', …)`
     * sem faixa nenhuma: um POST cru registrava pagamento **negativo** (que
     * baixava o `amount_paid` da fatura) ou dez vezes o total — e "1.234,56"
     * entrava como 1,23. O saldo vem da soma dos pagamentos, não da coluna
     * denormalizada `amount_paid`, e a comparação é em centavos.
     */
    private function amountFor(int $invoiceId, float $total, mixed $raw): float
    {
        $amount = Money::parse($raw);
        if ($amount === null || Money::cmp($amount, 0) <= 0 || !Money::inRange($amount)) {
            throw new ValidationException(t('finance.payment_amount_invalid'));
        }

        $remaining = round($total - $this->paymentRepo->totalByInvoice($invoiceId), 2);
        if (Money::cmp($remaining, 0) <= 0) {
            throw new ValidationException(t('finance.payment_no_balance'));
        }
        if (Money::cmp($amount, $remaining) > 0) {
            throw new ValidationException(t('finance.payment_above_balance', [
                'max' => number_format($remaining, 2, ',', '.'),
            ]));
        }

        return $amount;
    }

    /**
     * Forma de pagamento por allowlist: a coluna é `varchar(50)` livre e o
     * rótulo saía do banco. Vazio continua valendo "outro" (como antes); o que
     * é diferente é que forma inventada não entra mais.
     */
    private function method(mixed $raw): string
    {
        $method = is_string($raw) ? trim($raw) : '';
        if ($method === '') {
            return 'other';
        }
        if (!in_array($method, self::METHODS, true)) {
            throw new ValidationException(t('finance.payment_method_invalid'));
        }

        return $method;
    }

    /** @return int a fatura do pagamento removido — é para onde a tela volta (SEC-16). */
    public function delete(int $id): int
    {
        $payment = $this->paymentRepo->findByIdAndAgency($id, Auth::agencyId());
        if (!$payment) {
            throw new ValidationException(t('finance.payment_not_found'));
        }

        // O pagamento é da fatura de um cliente (SEC-11).
        Auth::requireClientAccess((int) $payment['client_id']);

        $invoiceId = (int) $payment['invoice_id'];

        $this->paymentRepo->deleteById($id, Auth::agencyId());
        $this->invoiceRepo->updateAmountPaid($invoiceId);
        $invoice = $this->invoiceRepo->findWithDetails($invoiceId, Auth::agencyId());
        if ($invoice) {
            $this->refreshInvoiceStatus($invoiceId, Auth::agencyId(), $invoice);
        }

        ActivityLogger::log('payment.deleted', 'finance', null, (int) $payment['client_id'], [
            'payment_id' => $id,
            'invoice_id' => $invoiceId,
            'amount'     => $payment['amount'] ?? null,
        ]);

        return $invoiceId;
    }

    public function summaryByMonth(string $year): array
    {
        return $this->paymentRepo->summaryByMonth(Auth::agencyId(), $year);
    }

    /**
     * Situação da fatura depois de mexer no pagamento. Comparação em centavos
     * (SEC-16): com `float`, três parcelas de 33,33 em cem reais não fechavam a
     * fatura como paga.
     *
     * @param array<string,mixed> $invoice
     */
    private function refreshInvoiceStatus(int $invoiceId, int $agencyId, array $invoice): void
    {
        // Cancelada é terminal, como em `InvoiceService::refreshStatus`: cancelar
        // uma fatura com pagamento é legítimo, e remover um dos pagamentos que
        // sobraram nela não pode devolvê-la para `partial`/`paid` (SEC-16).
        if (($invoice['status'] ?? '') === 'cancelled') return;

        $paid  = $this->paymentRepo->totalByInvoice($invoiceId);
        $total = (float) $invoice['total'];

        // Fatura sem valor (rascunho sem item, linha legada com total zerado):
        // dinheiro recebido ainda precisa aparecer como paga — o `return` seco
        // deixava a fatura "enviada" com pagamento registrado em cima.
        if (Money::cmp($total, 0) <= 0) {
            if (Money::cmp($paid, 0) > 0 && $invoice['status'] !== 'paid') {
                $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'paid', date('Y-m-d H:i:s'));
            }
            return;
        }

        if (Money::cmp($paid, $total) >= 0) {
            $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'paid', date('Y-m-d H:i:s'));
        } elseif (Money::cmp($paid, 0) > 0) {
            $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'partial');
        } else {
            if (in_array($invoice['status'], ['paid', 'partial'], true)) {
                $this->invoiceRepo->updateStatus($invoiceId, $agencyId, 'sent');
            }
        }
    }
}
