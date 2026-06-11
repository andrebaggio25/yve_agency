<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class ContractRepository extends Repository
{
    protected string $table = 'contracts';

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['c.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['client_id'])) {
            $where[] = 'c.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = 'c.title ILIKE :q';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        return $this->all("
            SELECT c.*, cl.name AS client_name
            FROM contracts c
            JOIN clients cl ON cl.id = c.client_id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC
        ", $params);
    }

    public function findWithClient(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT c.*, cl.name AS client_name, cl.currency_code AS client_currency
            FROM contracts c
            JOIN clients cl ON cl.id = c.client_id
            WHERE c.id = :id AND c.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT INTO contracts
                (agency_id, client_id, title, description, value, currency_code,
                 status, start_date, end_date, recurring, recurrence, notes, created_by, created_at, updated_at)
            VALUES
                (:agency_id, :client_id, :title, :description, :value, :currency_code,
                 :status, :start_date, :end_date, :recurring, :recurrence, :notes, :created_by, :now, :now)
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'    => $data['agency_id'],
            ':client_id'    => $data['client_id'],
            ':title'        => $data['title'],
            ':description'  => $data['description'] ?? null,
            ':value'        => $data['value'] ?? 0,
            ':currency_code'=> $data['currency_code'] ?? 'BRL',
            ':status'       => $data['status'] ?? 'draft',
            ':start_date'   => $data['start_date'] ?: null,
            ':end_date'     => $data['end_date'] ?: null,
            ':recurring'    => $data['recurring'] ? 1 : 0,
            ':recurrence'   => $data['recurrence'] ?: null,
            ':notes'        => $data['notes'] ?? null,
            ':created_by'   => $data['created_by'] ?? null,
            ':now'          => $now,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateById(int $id, int $agencyId, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->pdo->prepare("
            UPDATE contracts SET
                title        = :title,
                description  = :description,
                value        = :value,
                currency_code= :currency_code,
                status       = :status,
                start_date   = :start_date,
                end_date     = :end_date,
                signed_at    = :signed_at,
                recurring    = :recurring,
                recurrence   = :recurrence,
                notes        = :notes,
                updated_at   = :updated_at
            WHERE id = :id AND agency_id = :agency_id
        ")->execute([
            ':title'        => $data['title'],
            ':description'  => $data['description'] ?? null,
            ':value'        => $data['value'] ?? 0,
            ':currency_code'=> $data['currency_code'] ?? 'BRL',
            ':status'       => $data['status'] ?? 'draft',
            ':start_date'   => $data['start_date'] ?: null,
            ':end_date'     => $data['end_date'] ?: null,
            ':signed_at'    => $data['signed_at'] ?: null,
            ':recurring'    => $data['recurring'] ? 1 : 0,
            ':recurrence'   => $data['recurrence'] ?: null,
            ':notes'        => $data['notes'] ?? null,
            ':updated_at'   => $data['updated_at'],
            ':id'           => $id,
            ':agency_id'    => $agencyId,
        ]);
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM contracts WHERE id = :id AND agency_id = :agency_id"
        );
        $stmt->execute([':id' => $id, ':agency_id' => $agencyId]);
        return $stmt->rowCount() > 0;
    }

    public function findByClient(int $clientId): array
    {
        return $this->all("
            SELECT ct.*, c.name AS client_name
            FROM contracts ct
            LEFT JOIN clients c ON c.id = ct.client_id
            WHERE ct.client_id = :client_id
            ORDER BY ct.start_date DESC
        ", [':client_id' => $clientId]);
    }

    public function summaryByAgency(int $agencyId): array
    {
        return $this->first("
            SELECT
                COUNT(*)                                              AS total,
                COUNT(*) FILTER (WHERE status = 'active')            AS active,
                COUNT(*) FILTER (WHERE status = 'draft')             AS draft,
                COUNT(*) FILTER (WHERE status = 'expired')           AS expired,
                COALESCE(SUM(value) FILTER (WHERE status = 'active'), 0) AS active_value
            FROM contracts
            WHERE agency_id = :agency_id
        ", [':agency_id' => $agencyId]) ?? [];
    }

    /** Contratos ativos para combo de associação de fatura */
    public function activeForClient(int $clientId, int $agencyId): array
    {
        return $this->all("
            SELECT id, title FROM contracts
            WHERE client_id = :cid AND agency_id = :aid AND status = 'active'
            ORDER BY title
        ", [':cid' => $clientId, ':aid' => $agencyId]);
    }
}
