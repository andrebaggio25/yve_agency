<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdsActionRepository extends Repository
{
    protected string $table = 'ads_actions';

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ads_actions
                (agency_id, ad_account_id, campaign_id, ad_set_id, ad_id,
                 action_type, description, justification,
                 current_value, proposed_value,
                 status, ai_generated, requested_by,
                 created_at, updated_at)
            VALUES
                (:agency_id, :account_id, :campaign_id, :ad_set_id, :ad_id,
                 :action_type, :description, :justification,
                 :current_value, :proposed_value,
                 'pending', :ai_generated, :requested_by,
                 NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'     => $data['agency_id'],
            ':account_id'    => $data['ad_account_id'],
            ':campaign_id'   => $data['campaign_id'] ?? null,
            ':ad_set_id'     => $data['ad_set_id'] ?? null,
            ':ad_id'         => $data['ad_id'] ?? null,
            ':action_type'   => $data['action_type'],
            ':description'   => $data['description'],
            ':justification' => $data['justification'] ?? null,
            ':current_value' => $data['current_value'] ?? null,
            ':proposed_value'=> $data['proposed_value'] ?? null,
            ':ai_generated'  => $data['ai_generated'] ? 'true' : 'false',
            ':requested_by'  => $data['requested_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['aa.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['status'])) {
            $where[] = 'aa.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['ad_account_id'])) {
            $where[] = 'aa.ad_account_id = :account_id';
            $params[':account_id'] = (int) $filters['ad_account_id'];
        }

        $whereClause = implode(' AND ', $where);
        return $this->all("
            SELECT
                aa.*,
                a.name  AS account_name,
                c.name  AS campaign_name,
                s.name  AS ad_set_name,
                ad.name AS ad_name,
                ru.name AS requested_by_name,
                au.name AS approved_by_name
            FROM ads_actions aa
            JOIN ad_accounts a ON a.id = aa.ad_account_id
            LEFT JOIN campaigns c  ON c.id  = aa.campaign_id
            LEFT JOIN ad_sets   s  ON s.id  = aa.ad_set_id
            LEFT JOIN ads       ad ON ad.id = aa.ad_id
            LEFT JOIN users ru ON ru.id = aa.requested_by
            LEFT JOIN users au ON au.id = aa.approved_by
            WHERE {$whereClause}
            ORDER BY aa.created_at DESC
        ", $params);
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT
                aa.*,
                a.name  AS account_name,
                c.name  AS campaign_name,
                s.name  AS ad_set_name,
                ad.name AS ad_name,
                ru.name AS requested_by_name
            FROM ads_actions aa
            JOIN ad_accounts a ON a.id = aa.ad_account_id
            LEFT JOIN campaigns c  ON c.id  = aa.campaign_id
            LEFT JOIN ad_sets   s  ON s.id  = aa.ad_set_id
            LEFT JOIN ads       ad ON ad.id = aa.ad_id
            LEFT JOIN users ru ON ru.id = aa.requested_by
            WHERE aa.id = :id AND aa.agency_id = :agency_id
            LIMIT 1
        ", [':id' => $id, ':agency_id' => $agencyId]);
    }

    public function setStatus(int $id, string $status, ?int $userId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $approvedAt  = in_array($status, ['approved', 'rejected'], true) ? $now : null;
        $executedAt  = $status === 'executed' ? $now : null;

        $this->pdo->prepare("
            UPDATE ads_actions SET
                status      = :status,
                approved_by = CASE WHEN :user_id IS NOT NULL THEN :user_id2 ELSE approved_by END,
                approved_at = CASE WHEN :approved_at IS NOT NULL THEN :approved_at2::timestamp ELSE approved_at END,
                executed_at = CASE WHEN :executed_at IS NOT NULL THEN :executed_at2::timestamp ELSE executed_at END,
                updated_at  = NOW()
            WHERE id = :id
        ")->execute([
            ':status'      => $status,
            ':user_id'     => $userId,
            ':user_id2'    => $userId,
            ':approved_at' => $approvedAt,
            ':approved_at2'=> $approvedAt,
            ':executed_at' => $executedAt,
            ':executed_at2'=> $executedAt,
            ':id'          => $id,
        ]);
    }

    public function setError(int $id, string $error): void
    {
        $this->pdo->prepare("
            UPDATE ads_actions SET status = 'failed', error_message = :err, updated_at = NOW()
            WHERE id = :id
        ")->execute([':err' => $error, ':id' => $id]);
    }

    public function countPending(int $agencyId): int
    {
        $row = $this->first(
            "SELECT COUNT(*) AS n FROM ads_actions WHERE agency_id = :aid AND status = 'pending'",
            [':aid' => $agencyId]
        );
        return (int) ($row['n'] ?? 0);
    }
}
