<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class CampaignRepository extends Repository
{
    protected string $table = 'campaigns';

    public function listByAccount(int $adAccountId, array $filters = []): array
    {
        $where  = ['c.ad_account_id = :account_id'];
        $params = [':account_id' => $adAccountId];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = 'c.name ILIKE :q';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        return $this->all("
            SELECT c.*
            FROM campaigns c
            WHERE {$whereClause}
            ORDER BY c.name
        ", $params);
    }

    public function listByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['a.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['ad_account_id']) && $filters['ad_account_id']) {
            $where[] = 'c.ad_account_id = :account_id';
            $params[':account_id'] = (int) $filters['ad_account_id'];
        }

        $whereClause = implode(' AND ', $where);
        return $this->all("
            SELECT c.*, a.name AS account_name, a.currency, a.client_id,
                   cl.name AS client_name
            FROM campaigns c
            JOIN ad_accounts a ON a.id = c.ad_account_id
            LEFT JOIN clients cl ON cl.id = a.client_id
            WHERE {$whereClause}
            ORDER BY c.name
        ", $params);
    }

    public function findById(int $id): ?array
    {
        return $this->first("
            SELECT c.*, a.name AS account_name, a.currency, a.agency_id, a.client_id
            FROM campaigns c
            JOIN ad_accounts a ON a.id = c.ad_account_id
            WHERE c.id = :id LIMIT 1
        ", [':id' => $id]);
    }

    public function findByPlatformId(int $adAccountId, string $platformId): ?array
    {
        return $this->first(
            "SELECT * FROM campaigns WHERE ad_account_id = :aid AND platform_id = :pid LIMIT 1",
            [':aid' => $adAccountId, ':pid' => $platformId]
        );
    }

    public function upsert(int $adAccountId, string $platform, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO campaigns
                (ad_account_id, platform, platform_id, name, status, objective, buying_type,
                 daily_budget, lifetime_budget, start_time, stop_time, synced_at, created_at, updated_at)
            VALUES
                (:account_id, :platform, :platform_id, :name, :status, :objective, :buying_type,
                 :daily_budget, :lifetime_budget, :start_time, :stop_time, NOW(), NOW(), NOW())
            ON CONFLICT (ad_account_id, platform_id)
            DO UPDATE SET
                name            = EXCLUDED.name,
                status          = EXCLUDED.status,
                objective       = EXCLUDED.objective,
                buying_type     = EXCLUDED.buying_type,
                daily_budget    = EXCLUDED.daily_budget,
                lifetime_budget = EXCLUDED.lifetime_budget,
                start_time      = EXCLUDED.start_time,
                stop_time       = EXCLUDED.stop_time,
                synced_at       = NOW(),
                updated_at      = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':account_id'      => $adAccountId,
            ':platform'        => $platform,
            ':platform_id'     => $data['platform_id'],
            ':name'            => $data['name'],
            ':status'          => $data['status'],
            ':objective'       => $data['objective'] ?? null,
            ':buying_type'     => $data['buying_type'] ?? null,
            ':daily_budget'    => $data['daily_budget'] ?? null,
            ':lifetime_budget' => $data['lifetime_budget'] ?? null,
            ':start_time'      => $data['start_time'] ?? null,
            ':stop_time'       => $data['stop_time'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
