<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class TaskRepository extends Repository
{
    protected string $table = 'tasks';

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $sql = "
            SELECT t.*,
                   c.name  AS client_name,
                   u.name  AS assigned_name,
                   cb.name AS created_by_name
            FROM tasks t
            LEFT JOIN clients  c  ON c.id = t.client_id
            LEFT JOIN users    u  ON u.id = t.assigned_to
            LEFT JOIN users    cb ON cb.id = t.created_by
            WHERE t.agency_id = :agency_id
        ";
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= " AND t.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        $sql .= " ORDER BY
            CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
            t.due_date NULLS LAST,
            t.created_at DESC";

        return $this->all($sql, $params);
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT t.*,
                   c.name  AS client_name,
                   u.name  AS assigned_name,
                   cb.name AS created_by_name
            FROM tasks t
            LEFT JOIN clients  c  ON c.id = t.client_id
            LEFT JOIN users    u  ON u.id = t.assigned_to
            LEFT JOIN users    cb ON cb.id = t.created_by
            WHERE t.id = :id AND t.agency_id = :agency_id
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (agency_id, client_id, assigned_to, created_by, title, description, status, priority, due_date, created_at, updated_at)
            VALUES (:agency_id, :client_id, :assigned_to, :created_by, :title, :description, :status, :priority, :due_date, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'   => $data['agency_id'],
            ':client_id'   => ($data['client_id']   ?: null),
            ':assigned_to' => ($data['assigned_to'] ?: null),
            ':created_by'  => $data['created_by'],
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':status'      => $data['status']   ?? 'todo',
            ':priority'    => $data['priority'] ?? 'medium',
            ':due_date'    => $data['due_date'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, int $agencyId, array $data): void
    {
        $this->pdo->prepare("
            UPDATE tasks SET
                client_id   = :client_id,
                assigned_to = :assigned_to,
                title       = :title,
                description = :description,
                status      = :status,
                priority    = :priority,
                due_date    = :due_date,
                updated_at  = NOW()
            WHERE id = :id AND agency_id = :agency_id
        ")->execute([
            ':id'          => $id,
            ':agency_id'   => $agencyId,
            ':client_id'   => $data['client_id']   ?: null,
            ':assigned_to' => $data['assigned_to'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':status'      => $data['status']   ?? 'todo',
            ':priority'    => $data['priority'] ?? 'medium',
            ':due_date'    => $data['due_date'] ?: null,
        ]);
    }

    public function updateStatus(int $id, int $agencyId, string $status): void
    {
        $this->pdo->prepare(
            "UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id AND agency_id = :agency_id"
        )->execute([':id' => $id, ':agency_id' => $agencyId, ':status' => $status]);
    }

    public function deleteById(int $id, int $agencyId): void
    {
        $this->pdo->prepare(
            "DELETE FROM tasks WHERE id = :id AND agency_id = :agency_id"
        )->execute([':id' => $id, ':agency_id' => $agencyId]);
    }

    public function countByStatus(int $agencyId): array
    {
        $rows = $this->all("
            SELECT status, COUNT(*) AS total
            FROM tasks
            WHERE agency_id = :agency_id
            GROUP BY status
        ", [':agency_id' => $agencyId]);

        $counts = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['total'];
        }
        return $counts;
    }
}
