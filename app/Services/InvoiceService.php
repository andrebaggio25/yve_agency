<?php

declare(strict_types=1);

namespace App\Services;


use App\Core\ValidationException;
use App\Repositories\ContractRepository;
use App\Repositories\InvoiceRepository;
use App\Services\EmailService;
use App\Support\ActivityLogger;
use App\Support\Auth;
use App\Support\Money;

class InvoiceService
{
    /**
     * Situações que o formulário pode escolher. `paid`, `partial` e `overdue`
     * são **derivadas** (pagamento registrado, vencimento) e ficam de fora:
     * sem esta allowlist um POST cru criava fatura já `paid` com
     * `amount_paid = 0`, e o painel mostrava recebido o que ninguém pagou.
     */
    private const FORM_STATUSES = ['draft', 'sent', 'cancelled'];

    /** Situações que a própria fatura pode ter (inclui as derivadas). */
    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'];

    /** Limite de `invoice_items.description` (varchar(500)). */
    private const DESCRIPTION_MAX = 500;

    public function __construct(
        private InvoiceRepository $repo,
        private ContractRepository $contractRepo,
        private ?EmailService $emailService = null,
    ) {}

    public function list(array $filters = []): array
    {
        return $this->repo->listByAgency(Auth::agencyId(), $this->scopeToReach($filters));
    }

    public function listPaginated(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return $this->repo->listByAgencyPaginated(Auth::agencyId(), $this->scopeToReach($filters), $page, $perPage);
    }

    /**
     * Recorta a listagem aos clientes que o usuário alcança (SEC-11). Sem isto
     * o seletor escondia o cliente e a URL entregava a fatura dele: o papel
     * `client_financial` (só `invoices.view`) via a cobrança de toda a agência.
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

    public function find(int $id): ?array
    {
        return $this->repo->findWithDetails($id, Auth::agencyId());
    }

    public function findOrFail(int $id): array
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            http_response_code(404);
            throw new ValidationException(t('finance.invoice_not_found'));
        }

        // A fatura é de um cliente: o escopo de agência não basta (SEC-11).
        // 403 como no resto do alcance de cliente (ClientAccessMiddleware).
        Auth::requireClientAccess((int) $invoice['client_id']);

        return $invoice;
    }

    public function findWithItems(int $id): array
    {
        $invoice  = $this->findOrFail($id);
        $invoice['items']    = $this->repo->findItems($id);
        $invoice['payments'] = $this->repo->findPayments($id);
        return $invoice;
    }

    public function create(array $input, array $items = []): int
    {
        $agencyId = Auth::agencyId();
        $data     = $this->sanitize($input);
        // Itens e totais validados ANTES de gravar: recusar no meio custaria um
        // rollback e, na edição, apagaria os itens que já estavam na fatura.
        $items    = $this->sanitizeItems($items, (float) $data['discount'], (float) $data['tax']);
        $this->requireReachableClient((int) $data['client_id']);
        $data['agency_id']      = $agencyId;
        $data['created_by']     = Auth::id();
        $data['invoice_number'] = $this->repo->nextInvoiceNumber($agencyId);

        // Fatura e itens numa transação: erro no meio do laço de itens deixava
        // fatura órfã, sem item e com total zerado (SEC-16).
        $id = $this->repo->transaction(function () use ($data, $items): int {
            $id = $this->repo->create($data);
            $this->syncItems($id, $items, (float)($data['discount'] ?? 0), (float)($data['tax'] ?? 0));
            return $id;
        });

        // Trilha depois do commit (SEC-15): rollback não pode deixar log de
        // fatura que não existe.
        ActivityLogger::log('invoice.created', 'finance', null, (int) $data['client_id'], [
            'invoice_id'     => $id,
            'invoice_number' => $data['invoice_number'],
            'status'         => $data['status'],
            'items'          => count($items),
        ]);

        return $id;
    }

    public function update(int $id, array $input, array $items = []): void
    {
        $invoice = $this->findOrFail($id);
        $data    = $this->sanitize($input, $invoice);
        $items   = $this->sanitizeItems($items, (float) $data['discount'], (float) $data['tax']);
        $this->requireReachableClient((int) $data['client_id']);

        $this->repo->transaction(function () use ($id, $data, $items): void {
            $this->repo->updateById($id, Auth::agencyId(), $data);
            $this->syncItems($id, $items, (float)($data['discount'] ?? 0), (float)($data['tax'] ?? 0));

            // A situação é derivada: sem reavaliar depois de mexer no total, uma
            // fatura `paid` que ganha item segue "paga" com saldo em aberto — e
            // o devido some do `pending_total` do painel, que só soma
            // `sent|overdue|partial` (SEC-16).
            $this->refreshStatus($id, (int) Auth::agencyId());
        });

        // `status_requested` é o que veio do formulário; a situação final pode
        // ser outra, porque `refreshStatus` a re-deriva do saldo (SEC-16).
        ActivityLogger::log('invoice.updated', 'finance', null, (int) $data['client_id'], [
            'invoice_id'       => $id,
            'invoice_number'   => $invoice['invoice_number'] ?? null,
            'status_before'    => $invoice['status'] ?? null,
            'status_requested' => $data['status'],
            'items'            => count($items),
        ]);
    }

    /**
     * O `client_id` vem do formulário (SEC-11): sem isto a fatura nasce com
     * `agency_id` da sessão e cliente de outra agência — e a listagem passa a
     * exibir o nome dele. Escopo de agência não basta: quem não tem
     * `clients.view_all` só fatura os clientes que alcança.
     */
    private function requireReachableClient(int $clientId): void
    {
        if ($clientId <= 0 || !Auth::canAccessClient($clientId)) {
            throw new ValidationException(t('clients.out_of_reach'));
        }
    }

    public function delete(int $id): void
    {
        $invoice = $this->findOrFail($id);   // alcance do cliente antes de apagar (SEC-11)

        // `payments.invoice_id` é ON DELETE RESTRICT: sem esta checagem o
        // Postgres recusava e a tela mostrava o SQLSTATE 23503 cru (SEC-16).
        if ($this->repo->findPayments($id) !== []) {
            throw new ValidationException(t('finance.invoice_has_payments'));
        }

        if (!$this->repo->deleteById($id, Auth::agencyId())) {
            throw new ValidationException(t('finance.invoice_not_found'));
        }

        ActivityLogger::log('invoice.deleted', 'finance', null, (int) $invoice['client_id'], [
            'invoice_id'     => $id,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'total'          => $invoice['total'] ?? null,
        ]);
    }

    public function markSent(int $id): void
    {
        $invoice = $this->findOrFail($id);
        if ($invoice['status'] !== 'draft') return;
        $this->repo->updateStatus($id, Auth::agencyId(), 'sent');

        ActivityLogger::log('invoice.marked_sent', 'finance', null, (int) $invoice['client_id'], [
            'invoice_id'     => $id,
            'invoice_number' => $invoice['invoice_number'] ?? null,
        ]);
    }

    public function markOverdue(int $id): void
    {
        $this->repo->updateStatus($id, Auth::agencyId(), 'overdue');
    }

    public function refreshStatus(int $id, int $agencyId): void
    {
        $invoice = $this->repo->findWithDetails($id, $agencyId);
        if (!$invoice) return;

        // Cancelada é **terminal**: é decisão de quem cobra, não situação
        // derivada de saldo. Sem esta saída, nenhuma fatura com pagamento podia
        // mais ser cancelada — `update` gravava `cancelled` e a re-derivação
        // devolvia `partial`/`paid` na linha seguinte, com a tela dizendo
        // "Fatura atualizada"; e fatura já cancelada com pagamento voltava para
        // o faturado do painel ao salvar qualquer campo (SEC-16).
        if ($invoice['status'] === 'cancelled') return;

        $paid  = (float) $invoice['amount_paid'];
        $total = (float) $invoice['total'];
        $status = $invoice['status'];

        // Fatura sem valor (rascunho sem item, linha legada): só reage se houve
        // dinheiro recebido — senão `0 >= 0` marcaria todo rascunho como paga.
        if (Money::cmp($total, 0) <= 0) {
            if (Money::cmp($paid, 0) > 0 && $status !== 'paid') {
                $this->repo->updateStatus($id, $agencyId, 'paid', $invoice['paid_at'] ?? date('Y-m-d H:i:s'));
            }
            return;
        }

        if (Money::cmp($paid, $total) >= 0) {
            $newStatus = 'paid';
            $paidAt    = $invoice['paid_at'] ?? date('Y-m-d H:i:s');
        } elseif (Money::cmp($paid, 0) > 0) {
            $newStatus = 'partial';
            $paidAt    = null;
        } else {
            return;
        }

        if ($status !== $newStatus) {
            $this->repo->updateStatus($id, $agencyId, $newStatus, $paidAt ?? null);
        }
    }

    public function sendByEmail(int $id, string $recipientEmail, string $recipientName): array
    {
        $invoice = $this->findWithItems($id);

        if (!$this->emailService) {
            return ['success' => false, 'error' => 'EmailService não configurado.'];
        }

        $vars = [
            'client_name'    => $recipientName,
            'invoice_number' => $invoice['invoice_number'],
            'invoice_title'  => $invoice['title'],
            'due_date'       => $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : null,
            'total'          => 'R$ ' . number_format((float)$invoice['total'], 2, ',', '.'),
            'notes'          => $invoice['notes'] ?? '',
        ];

        // Anexa a fatura em PDF (UX-04). É como cobrança circula: ninguém quer
        // um link para uma tela de impressão — quer o documento no e-mail.
        // Falha ao gerar o PDF não pode impedir o envio da cobrança.
        $attachments = [];
        try {
            $pdf  = new PdfService();
            $file = storage_path('exports/' . $pdf->filename('fatura', (string) $invoice['invoice_number']));

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            file_put_contents($file, $pdf->fromView('faturas.print', compact('invoice')));

            $attachments[] = $file;
        } catch (\Throwable $e) {
            error_log('[invoice] falha ao gerar PDF da fatura: ' . $e->getMessage());
        }

        $result = $attachments
            ? $this->emailService->sendWithAttachment($recipientEmail, $recipientName, 'invoice_sent', $vars, 'pt', $attachments)
            : $this->emailService->send($recipientEmail, $recipientName, 'invoice_sent', $vars);

        foreach ($attachments as $tmp) {
            @unlink($tmp);
        }

        // Cobrança que sai para fora da agência deixa rastro (SEC-15): o
        // destinatário vem do formulário, então é ele que importa no log.
        ActivityLogger::log('invoice.sent_by_email', 'finance', null, (int) $invoice['client_id'], [
            'invoice_id'     => $id,
            'invoice_number' => $invoice['invoice_number'],
            'recipient'      => $recipientEmail,
            'success'        => (bool) ($result['success'] ?? false),
        ]);

        return $result;
    }

    public function summary(): array
    {
        return $this->repo->summaryByAgency(Auth::agencyId());
    }

    /**
     * Um UPDATE por agência (SEC-16). Era um UPDATE por fatura vencida, a cada
     * carga de `/financeiro` e `/faturas` — numa agência com 300 faturas
     * atrasadas, 300 idas ao banco só para desenhar a lista.
     */
    public function markOverdueAll(): void
    {
        $this->repo->markOverdueByAgency((int) Auth::agencyId());
    }

    /**
     * @param array<int,array<string,mixed>> $items já normalizados por sanitizeItems
     */
    private function syncItems(int $invoiceId, array $items, float $discount, float $tax): void
    {
        $this->repo->deleteItems($invoiceId);
        foreach ($items as $i => $item) {
            $item['sort_order'] = $i;
            $this->repo->addItem($invoiceId, $item);
        }
        $this->repo->recalcTotals($invoiceId, $discount, $tax);
    }

    /**
     * Normaliza os itens e confere se o total cabe na coluna (SEC-16).
     *
     * O desconto era ilimitado: `recalcTotals` grava
     * `total = subtotal - desconto + imposto`, então um POST com desconto maior
     * que o subtotal deixava a fatura com **total negativo** — o painel somava
     * isso no faturado e nenhum pagamento fechava a fatura.
     *
     * @param array<int|string,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeItems(array $items, float $discount, float $tax): array
    {
        $clean    = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;   // linha em branco do formulário (o Alpine sempre manda uma)
            }

            // `invoice_items.description` é varchar(500). Sem esta checagem o
            // INSERT estourava com SQLSTATE 22001 **no meio** do laço de itens.
            if (mb_strlen($description) > self::DESCRIPTION_MAX) {
                throw new ValidationException(t('finance.item_description_too_long', [
                    'max' => (string) self::DESCRIPTION_MAX,
                ]));
            }

            // quantity é DECIMAL(10,3); unit_price, DECIMAL(12,2).
            $quantity  = Money::parse($item['quantity']   ?? 1, 3);
            $unitPrice = Money::parse($item['unit_price'] ?? 0);

            if ($quantity === null || $unitPrice === null || $quantity < 0 || $unitPrice < 0
                || $quantity > 9999999.999 || !Money::inRange($unitPrice)) {
                throw new ValidationException(t('finance.item_amount_invalid'));
            }

            $totalPrice = round($quantity * $unitPrice, 2);
            $subtotal   = round($subtotal + $totalPrice, 2);

            $clean[] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total_price' => $totalPrice,
            ];
        }

        if (Money::cmp($discount, $subtotal) > 0) {
            throw new ValidationException(t('finance.discount_above_subtotal'));
        }

        if (!Money::inRange($subtotal) || !Money::inRange(round($subtotal - $discount + $tax, 2))) {
            throw new ValidationException(t('finance.amount_out_of_range'));
        }

        return $clean;
    }

    /**
     * Valida o que o formulário manda (SEC-16): título obrigatório, situação por
     * allowlist e dinheiro lido com `Money` (antes, `(float) str_replace(…)`
     * transformava "1.234,56" em 1.23 e qualquer texto em 0).
     *
     * `$current` é a fatura em edição, quando houver: a tela de editar não
     * oferece as situações **derivadas** (`paid`, `partial`, `overdue`), então
     * numa fatura nesse estado o navegador manda a primeira opção do `<select>`
     * — salvar só o título devolvia fatura paga para rascunho, calada. A view
     * passou a manter a opção atual; aqui está a trava do lado do servidor.
     *
     * @param array<string,mixed>      $input
     * @param array<string,mixed>|null $current
     * @return array<string,mixed>
     */
    private function sanitize(array $input, ?array $current = null): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(t('finance.title_required'));
        }

        $allowedStatuses = self::FORM_STATUSES;
        $currentStatus   = (string) ($current['status'] ?? '');
        if (in_array($currentStatus, self::STATUSES, true)) {
            $allowedStatuses[] = $currentStatus;
        }

        $status = trim((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException(t('finance.status_invalid'));
        }

        return [
            'client_id'    => (int) ($input['client_id'] ?? 0),
            'contract_id'  => $this->contractId($input['contract_id'] ?? null),
            'title'        => $title,
            'status'       => $status,
            'subtotal'     => 0,
            'discount'     => $this->money($input['discount'] ?? null, 'finance.discount_invalid'),
            'tax'          => $this->money($input['tax'] ?? null, 'finance.tax_invalid'),
            'total'        => 0,
            'currency_code'=> $input['currency_code'] ?? 'BRL',
            'due_date'     => ($input['due_date'] ?? '') ?: null,
            'notes'        => trim($input['notes'] ?? '') ?: null,
        ];
    }

    /**
     * O contrato do formulário é um id de outra tabela multi-tenant e entrava
     * cru (SEC-16): a FK só exige que o contrato **exista**, não que seja da
     * agência, então um POST com o id de um contrato alheio vinculava a fatura a
     * ele — e o título dele saía na listagem e no PDF que vai anexado ao e-mail
     * do cliente. Vazio continua valendo "sem contrato".
     */
    private function contractId(mixed $raw): ?int
    {
        $value = is_string($raw) ? trim($raw) : $raw;
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;
        if ($id <= 0 || !$this->contractRepo->findWithClient($id, (int) Auth::agencyId())) {
            throw new ValidationException(t('finance.contract_out_of_agency'));
        }

        return $id;
    }

    /** Campo de dinheiro do formulário: vazio vale 0; negativo ou fora da faixa recusa. */
    private function money(mixed $raw, string $errorKey): float
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return 0.0;
        }

        $value = Money::parse($raw);
        if ($value === null || $value < 0 || !Money::inRange($value)) {
            throw new ValidationException(t($errorKey));
        }

        return $value;
    }
}
