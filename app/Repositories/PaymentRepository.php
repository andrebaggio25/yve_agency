<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class PaymentRepository extends Repository
{
    protected string $table = 'payments';

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['p.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['invoice_id'])) {
            $where[] = 'p.invoice_id = :invoice_id';
            $params[':invoice_id'] = (int) $filters['invoice_id'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'i.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->all("
            SELECT p.*, i.invoice_number, i.title AS invoice_title, cl.name AS client_name
            FROM payments p
            JOIN invoices i  ON i.id = p.invoice_id
            JOIN clients  cl ON cl.id = i.client_id
            WHERE {$whereClause}
            ORDER BY p.payment_date DESC, p.id DESC
        ", $params);
    }

    public function findById(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT p.*, i.invoice_number, i.title AS invoice_title, cl.name AS client_name
            FROM payments p
            JOIN invoices i  ON i.id = p.invoice_id
            JOIN clients  cl ON cl.id = i.client_id
            WHERE p.id = :id AND p.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments
                (agency_id, invoice_id, amount, payment_method, payment_date,
                 reference, notes, created_by, created_at, updated_at)
            VALUES
                (:agency_id, :invoice_id, :amount, :payment_method, :payment_date,
                 :reference, :notes, :created_by, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'      => $data['agency_id'],
            ':invoice_id'     => $data['invoice_id'],
            ':amount'         => $data['amount'],
            ':payment_method' => $data['payment_method'] ?? 'other',
            ':payment_date'   => $data['payment_date'],
            ':reference'      => $data['reference'] ?? null,
            ':notes'          => $data['notes'] ?? null,
            ':created_by'     => $data['created_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM payments WHERE id = :id AND agency_id = :agency_id"
        );
        $stmt->execute([':id' => $id, ':agency_id' => $agencyId]);
        return $stmt->rowCount() > 0;
    }

    public function totalByInvoice(int $invoiceId): float
    {
        $row = $this->first(
            "SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE invoice_id = :id",
            [':id' => $invoiceId]
        );
        return (float) ($row['total'] ?? 0);
    }

    public function summaryByMonth(int $agencyId, string $year): array
    {
        return $this->all("
            SELECT
                TO_CHAR(payment_date, 'YYYY-MM') AS month,
                SUM(amount)                       AS total
            FROM payments
            WHERE agency_id = :agency_id
              AND EXTRACT(YEAR FROM payment_date) = :year
            GROUP BY month
            ORDER BY month
        ", [':agency_id' => $agencyId, ':year' => $year]);
    }
}
