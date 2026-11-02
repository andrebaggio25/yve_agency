<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class InvoiceRepository extends Repository
{
    protected string $table = 'invoices';

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['i.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['client_id'])) {
            $where[] = 'i.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (isset($filters['client_ids'])) {
            $where[] = $this->clientIdsCondition((array) $filters['client_ids'], $params, 'i.client_id');
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(i.title ILIKE :q OR i.invoice_number ILIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        return $this->all("
            SELECT i.*, cl.name AS client_name, co.title AS contract_title
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            -- A agência no JOIN (SEC-16): `contract_id` chegava cru do POST e a
            -- FK só exige que o contrato exista, então fatura apontando para
            -- contrato de outra agência exibia o título dele aqui e no PDF.
            LEFT JOIN contracts co ON co.id = i.contract_id AND co.agency_id = i.agency_id
            WHERE {$whereClause}
            ORDER BY i.created_at DESC
        ", $params);
    }

    public function listByAgencyPaginated(int $agencyId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['i.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['client_id'])) {
            $where[] = 'i.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (isset($filters['client_ids'])) {
            $where[] = $this->clientIdsCondition((array) $filters['client_ids'], $params, 'i.client_id');
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(i.title ILIKE :q OR i.invoice_number ILIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        return $this->paginate("
            SELECT i.*, cl.name AS client_name
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            WHERE {$whereClause}
            ORDER BY i.created_at DESC
        ", $params, $page, $perPage);
    }

    public function findWithDetails(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT i.*, cl.name AS client_name, co.title AS contract_title
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            LEFT JOIN contracts co ON co.id = i.contract_id AND co.agency_id = i.agency_id
            WHERE i.id = :id AND i.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function findItems(int $invoiceId): array
    {
        return $this->all(
            "SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order, id",
            [':id' => $invoiceId]
        );
    }

    public function findPayments(int $invoiceId): array
    {
        return $this->all(
            "SELECT * FROM payments WHERE invoice_id = :id ORDER BY payment_date DESC",
            [':id' => $invoiceId]
        );
    }

    public function nextInvoiceNumber(int $agencyId): string
    {
        $row = $this->first(
            "SELECT MAX(CAST(REGEXP_REPLACE(invoice_number,'[^0-9]','','g') AS BIGINT)) AS mx
             FROM invoices WHERE agency_id = :aid",
            [':aid' => $agencyId]
        );
        $next = (int) ($row['mx'] ?? 0) + 1;
        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Fatura e itens gravam juntos ou não gravam (SEC-16): `syncItems` apaga os
     * itens antigos e reinsere um por um, então um erro no meio deixava a fatura
     * existente **sem item** e com total zerado — e `recalcTotals`, que é a
     * última linha, nunca chegava a consertar. Mesma forma de
     * `ContentPlanRepository::moveItemToPlan`: se alguém já abriu transação, não
     * abrimos outra.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $own = $this->pdo->inTransaction() === false;
        if ($own) $this->pdo->beginTransaction();

        try {
            $result = $fn();
            if ($own) $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($own && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function create(array $data): int
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT INTO invoices
                (agency_id, client_id, contract_id, invoice_number, title, status,
                 subtotal, discount, tax, total, amount_paid, currency_code,
                 due_date, notes, created_by, created_at, updated_at)
            VALUES
                (:agency_id, :client_id, :contract_id, :invoice_number, :title, :status,
                 :subtotal, :discount, :tax, :total, 0, :currency_code,
                 :due_date, :notes, :created_by, :now, :now)
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'      => $data['agency_id'],
            ':client_id'      => $data['client_id'],
            ':contract_id'    => $data['contract_id'] ?: null,
            ':invoice_number' => $data['invoice_number'],
            ':title'          => $data['title'],
            ':status'         => $data['status'] ?? 'draft',
            ':subtotal'       => $data['subtotal'] ?? 0,
            ':discount'       => $data['discount'] ?? 0,
            ':tax'            => $data['tax'] ?? 0,
            ':total'          => $data['total'] ?? 0,
            ':currency_code'  => $data['currency_code'] ?? 'BRL',
            ':due_date'       => $data['due_date'] ?: null,
            ':notes'          => $data['notes'] ?? null,
            ':created_by'     => $data['created_by'] ?? null,
            ':now'            => $now,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateById(int $id, int $agencyId, array $data): void
    {
        $this->pdo->prepare("
            UPDATE invoices SET
                client_id    = :client_id,
                contract_id  = :contract_id,
                title        = :title,
                status       = :status,
                subtotal     = :subtotal,
                discount     = :discount,
                tax          = :tax,
                total        = :total,
                currency_code= :currency_code,
                due_date     = :due_date,
                notes        = :notes,
                updated_at   = NOW()
            WHERE id = :id AND agency_id = :agency_id
        ")->execute([
            ':client_id'    => $data['client_id'],
            ':contract_id'  => $data['contract_id'] ?: null,
            ':title'        => $data['title'],
            ':status'       => $data['status'] ?? 'draft',
            ':subtotal'     => $data['subtotal'] ?? 0,
            ':discount'     => $data['discount'] ?? 0,
            ':tax'          => $data['tax'] ?? 0,
            ':total'        => $data['total'] ?? 0,
            ':currency_code'=> $data['currency_code'] ?? 'BRL',
            ':due_date'     => $data['due_date'] ?: null,
            ':notes'        => $data['notes'] ?? null,
            ':id'           => $id,
            ':agency_id'    => $agencyId,
        ]);
    }

    public function updateStatus(int $id, int $agencyId, string $status, ?string $paidAt = null): void
    {
        $this->pdo->prepare("
            UPDATE invoices SET status = :status, paid_at = :paid_at, updated_at = NOW()
            WHERE id = :id AND agency_id = :agency_id
        ")->execute([':status' => $status, ':paid_at' => $paidAt, ':id' => $id, ':agency_id' => $agencyId]);
    }

    public function updateAmountPaid(int $id): void
    {
        $this->pdo->prepare("
            UPDATE invoices SET
                amount_paid = COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = :id2), 0),
                updated_at  = NOW()
            WHERE id = :id
        ")->execute([':id2' => $id, ':id' => $id]);
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM invoices WHERE id = :id AND agency_id = :agency_id"
        );
        $stmt->execute([':id' => $id, ':agency_id' => $agencyId]);
        return $stmt->rowCount() > 0;
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    public function addItem(int $invoiceId, array $item): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price, sort_order, created_at)
            VALUES (:invoice_id, :description, :quantity, :unit_price, :total_price, :sort_order, NOW())
            RETURNING id
        ");
        $qty   = (float) ($item['quantity']  ?? 1);
        $price = (float) ($item['unit_price'] ?? 0);
        $stmt->execute([
            ':invoice_id'  => $invoiceId,
            ':description' => $item['description'],
            ':quantity'    => $qty,
            ':unit_price'  => $price,
            // Arredondar aqui: a coluna tem 2 casas e o produto em float chega
            // com cauda (1.005 * 3), que o Postgres arredondaria sozinho — com
            // o subtotal que o serviço validou ficando fora por um centavo.
            ':total_price' => round($qty * $price, 2),
            ':sort_order'  => $item['sort_order'] ?? 0,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteItems(int $invoiceId): void
    {
        $this->pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = :id")
            ->execute([':id' => $invoiceId]);
    }

    public function recalcTotals(int $id, float $discount = 0, float $tax = 0): void
    {
        $this->pdo->prepare("
            UPDATE invoices SET
                subtotal   = COALESCE((SELECT SUM(total_price) FROM invoice_items WHERE invoice_id = :id2), 0),
                discount   = :discount,
                tax        = :tax,
                total      = COALESCE((SELECT SUM(total_price) FROM invoice_items WHERE invoice_id = :id3), 0)
                             - :discount2 + :tax2,
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':id2'       => $id,
            ':discount'  => $discount,
            ':tax'       => $tax,
            ':id3'       => $id,
            ':discount2' => $discount,
            ':tax2'      => $tax,
            ':id'        => $id,
        ]);
    }

    /**
     * Faturas de um cliente. A agência é **obrigatória** (SEC-16): quem chama é
     * o portal, com o cliente já resolvido pelo token, e uma query de tabela
     * multi-tenant sem `agency_id` depende de quem chama para não vazar.
     */
    public function findByClient(int $clientId, int $agencyId): array
    {
        return $this->all("
            SELECT i.*, c.name AS client_name
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id AND c.agency_id = i.agency_id
            WHERE i.client_id = :client_id AND i.agency_id = :agency_id
            ORDER BY i.due_date DESC
        ", [':client_id' => $clientId, ':agency_id' => $agencyId]);
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    public function summaryByAgency(int $agencyId): array
    {
        return $this->first("
            SELECT
                COUNT(*)                                                   AS total,
                COUNT(*) FILTER (WHERE status = 'draft')                   AS draft,
                COUNT(*) FILTER (WHERE status = 'sent')                    AS sent,
                COUNT(*) FILTER (WHERE status = 'paid')                    AS paid,
                COUNT(*) FILTER (WHERE status = 'overdue')                 AS overdue,
                COALESCE(SUM(total)       FILTER (WHERE status != 'cancelled'), 0)    AS billed_total,
                COALESCE(SUM(amount_paid) FILTER (WHERE status != 'cancelled'), 0)    AS received_total,
                COALESCE(SUM(total - amount_paid) FILTER (WHERE status IN ('sent','overdue','partial')), 0) AS pending_total
            FROM invoices
            WHERE agency_id = :agency_id
        ", [':agency_id' => $agencyId]) ?? [];
    }

    /**
     * Marca as faturas vencidas da agência num só UPDATE (SEC-16) — era um
     * SELECT + um UPDATE por fatura, a cada carga de `/financeiro` e `/faturas`.
     * Mesma forma que a automação `billing.mark_overdue` já usava.
     *
     * @return int quantas faturas mudaram de situação
     */
    public function markOverdueByAgency(int $agencyId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE invoices SET status = 'overdue', updated_at = NOW()
            WHERE agency_id = :agency_id AND status = 'sent'
              AND due_date < CURRENT_DATE
        ");
        $stmt->execute([':agency_id' => $agencyId]);

        return $stmt->rowCount();
    }
}
