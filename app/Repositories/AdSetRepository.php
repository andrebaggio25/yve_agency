<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdSetRepository extends Repository
{
    protected string $table = 'ad_sets';

    public function listByCampaign(int $campaignId): array
    {
        return $this->all(
            "SELECT * FROM ad_sets WHERE campaign_id = :cid ORDER BY name",
            [':cid' => $campaignId]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->first("SELECT * FROM ad_sets WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    public function upsert(int $campaignId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_sets
                (campaign_id, platform_id, name, status, daily_budget, lifetime_budget,
                 optimization_goal, billing_event, bid_strategy, bid_amount,
                 targeting_summary, start_time, stop_time, synced_at, created_at, updated_at)
            VALUES
                (:campaign_id, :platform_id, :name, :status, :daily_budget, :lifetime_budget,
                 :optimization_goal, :billing_event, :bid_strategy, :bid_amount,
                 :targeting_summary, :start_time, :stop_time, NOW(), NOW(), NOW())
            ON CONFLICT (campaign_id, platform_id)
            DO UPDATE SET
                name              = EXCLUDED.name,
                status            = EXCLUDED.status,
                daily_budget      = EXCLUDED.daily_budget,
                lifetime_budget   = EXCLUDED.lifetime_budget,
                optimization_goal = EXCLUDED.optimization_goal,
                billing_event     = EXCLUDED.billing_event,
                bid_strategy      = EXCLUDED.bid_strategy,
                bid_amount        = EXCLUDED.bid_amount,
                targeting_summary = EXCLUDED.targeting_summary,
                start_time        = EXCLUDED.start_time,
                stop_time         = EXCLUDED.stop_time,
                synced_at         = NOW(),
                updated_at        = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':campaign_id'      => $campaignId,
            ':platform_id'      => $data['platform_id'],
            ':name'             => $data['name'],
            ':status'           => $data['status'],
            ':daily_budget'     => $data['daily_budget'] ?? null,
            ':lifetime_budget'  => $data['lifetime_budget'] ?? null,
            ':optimization_goal'=> $data['optimization_goal'] ?? null,
            ':billing_event'    => $data['billing_event'] ?? null,
            ':bid_strategy'     => $data['bid_strategy'] ?? null,
            ':bid_amount'       => $data['bid_amount'] ?? null,
            ':targeting_summary'=> $data['targeting_summary'] ?? null,
            ':start_time'       => $data['start_time'] ?? null,
            ':stop_time'        => $data['stop_time'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
