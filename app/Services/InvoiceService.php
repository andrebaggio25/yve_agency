<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InvoiceRepository;
use App\Services\EmailService;
use App\Support\Auth;

class InvoiceService
{
    public function __construct(
        private InvoiceRepository $repo,
        private ?EmailService $emailService = null,
    ) {}

    public function list(array $filters = []): array
    {
        return $this->repo->listByAgency(Auth::agencyId(), $filters);
    }

    public function listPaginated(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return $this->repo->listByAgencyPaginated(Auth::agencyId(), $filters, $page, $perPage);
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
            throw new \RuntimeException("Fatura não encontrada.");
        }
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
        $data['agency_id']      = $agencyId;
        $data['created_by']     = Auth::id();
        $data['invoice_number'] = $this->repo->nextInvoiceNumber($agencyId);

        $id = $this->repo->create($data);
        $this->syncItems($id, $items, (float)($data['discount'] ?? 0), (float)($data['tax'] ?? 0));
        return $id;
    }

    public function update(int $id, array $input, array $items = []): void
    {
        $this->findOrFail($id);
        $data = $this->sanitize($input);
        $this->repo->updateById($id, Auth::agencyId(), $data);
        $this->syncItems($id, $items, (float)($data['discount'] ?? 0), (float)($data['tax'] ?? 0));
    }

    public function delete(int $id): void
    {
        if (!$this->repo->deleteById($id, Auth::agencyId())) {
            throw new \RuntimeException("Fatura não encontrada.");
        }
    }

    public function markSent(int $id): void
    {
        $invoice = $this->findOrFail($id);
        if ($invoice['status'] !== 'draft') return;
        $this->repo->updateStatus($id, Auth::agencyId(), 'sent');
    }

    public function markOverdue(int $id): void
    {
        $this->repo->updateStatus($id, Auth::agencyId(), 'overdue');
    }

    public function refreshStatus(int $id, int $agencyId): void
    {
        $invoice = $this->repo->findWithDetails($id, $agencyId);
        if (!$invoice) return;

        $paid  = (float) $invoice['amount_paid'];
        $total = (float) $invoice['total'];
        $status = $invoice['status'];

        if ($total <= 0) return;

        if ($paid >= $total) {
            $newStatus = 'paid';
            $paidAt    = $invoice['paid_at'] ?? date('Y-m-d H:i:s');
        } elseif ($paid > 0) {
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

        return $this->emailService->send($recipientEmail, $recipientName, 'invoice_sent', $vars);
    }

    public function summary(): array
    {
        return $this->repo->summaryByAgency(Auth::agencyId());
    }

    public function markOverdueAll(): void
    {
        $agencyId = Auth::agencyId();
        foreach ($this->repo->overdueToMark($agencyId) as $row) {
            $this->repo->updateStatus((int)$row['id'], $agencyId, 'overdue');
        }
    }

    private function syncItems(int $invoiceId, array $items, float $discount, float $tax): void
    {
        $this->repo->deleteItems($invoiceId);
        foreach ($items as $i => $item) {
            if (empty(trim($item['description'] ?? ''))) continue;
            $item['sort_order'] = $i;
            $this->repo->addItem($invoiceId, $item);
        }
        $this->repo->recalcTotals($invoiceId, $discount, $tax);
    }

    private function sanitize(array $input): array
    {
        return [
            'client_id'    => (int) ($input['client_id'] ?? 0),
            'contract_id'  => $input['contract_id'] ?: null,
            'title'        => trim($input['title'] ?? ''),
            'status'       => $input['status'] ?? 'draft',
            'subtotal'     => 0,
            'discount'     => (float) str_replace(',', '.', $input['discount'] ?? '0'),
            'tax'          => (float) str_replace(',', '.', $input['tax'] ?? '0'),
            'total'        => 0,
            'currency_code'=> $input['currency_code'] ?? 'BRL',
            'due_date'     => $input['due_date'] ?: null,
            'notes'        => trim($input['notes'] ?? '') ?: null,
        ];
    }
}
