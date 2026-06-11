<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AiInsightRepository extends Repository
{
    protected string $table = 'ai_insights';

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_insights
                (agency_id, client_id, ad_account_id, type, period_start, period_end,
                 content, metrics_snapshot, ai_provider, model, created_at)
            VALUES
                (:agency_id, :client_id, :ad_account_id, :type, :period_start, :period_end,
                 :content, :metrics_snapshot, :ai_provider, :model, NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'        => $data['agency_id'],
            ':client_id'        => $data['client_id'] ?? null,
            ':ad_account_id'    => $data['ad_account_id'] ?? null,
            ':type'             => $data['type'],
            ':period_start'     => $data['period_start'] ?? null,
            ':period_end'       => $data['period_end'] ?? null,
            ':content'          => $data['content'],
            ':metrics_snapshot' => isset($data['metrics_snapshot']) ? json_encode($data['metrics_snapshot']) : null,
            ':ai_provider'      => $data['ai_provider'] ?? null,
            ':model'            => $data['model'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['i.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['ad_account_id'])) {
            $where[] = 'i.ad_account_id = :account_id';
            $params[':account_id'] = (int) $filters['ad_account_id'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'i.type = :type';
            $params[':type'] = $filters['type'];
        }

        $whereClause = implode(' AND ', $where);
        return $this->all("
            SELECT i.*, a.name AS account_name, c.name AS client_name
            FROM ai_insights i
            LEFT JOIN ad_accounts a ON a.id = i.ad_account_id
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE {$whereClause}
            ORDER BY i.created_at DESC
            LIMIT 50
        ", $params);
    }

    public function findById(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT i.*, a.name AS account_name, c.name AS client_name
            FROM ai_insights i
            LEFT JOIN ad_accounts a ON a.id = i.ad_account_id
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE i.id = :id AND i.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM ai_insights WHERE id = :id AND agency_id = :agency_id"
        );
        $stmt->execute([':id' => $id, ':agency_id' => $agencyId]);
        return $stmt->rowCount() > 0;
    }

    public function countByAccount(int $accountId): int
    {
        $row = $this->first(
            "SELECT COUNT(*) AS n FROM ai_insights WHERE ad_account_id = :id",
            [':id' => $accountId]
        );
        return (int) ($row['n'] ?? 0);
    }
}
