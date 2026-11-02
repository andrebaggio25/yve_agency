<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdRepository extends Repository
{
    protected string $table = 'ads';

    public function listByAdSet(int $adSetId): array
    {
        return $this->all(
            "SELECT * FROM ads WHERE ad_set_id = :id ORDER BY name",
            [':id' => $adSetId]
        );
    }

    public function listByCampaign(int $campaignId): array
    {
        return $this->all("
            SELECT ads.*, s.name AS ad_set_name
            FROM ads
            JOIN ad_sets s ON s.id = ads.ad_set_id
            WHERE s.campaign_id = :cid
            ORDER BY s.name, ads.name
        ", [':cid' => $campaignId]);
    }

    public function findById(int $id): ?array
    {
        return $this->first("SELECT * FROM ads WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    /** Anúncio dentro de uma conta específica — alvo de ação validado (SEC-13). */
    public function findByIdAndAccount(int $id, int $adAccountId): ?array
    {
        return $this->first("
            SELECT ads.*
            FROM ads
            JOIN ad_sets   s ON s.id = ads.ad_set_id
            JOIN campaigns c ON c.id = s.campaign_id
            WHERE ads.id = :id AND c.ad_account_id = :account_id
            LIMIT 1
        ", [':id' => $id, ':account_id' => $adAccountId]);
    }

    public function upsert(int $adSetId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ads
                (ad_set_id, platform_id, name, status, creative_type,
                 headline, body, image_url, thumbnail_url, call_to_action, destination_url,
                 synced_at, created_at, updated_at)
            VALUES
                (:ad_set_id, :platform_id, :name, :status, :creative_type,
                 :headline, :body, :image_url, :thumbnail_url, :call_to_action, :destination_url,
                 NOW(), NOW(), NOW())
            ON CONFLICT (ad_set_id, platform_id)
            DO UPDATE SET
                name            = EXCLUDED.name,
                status          = EXCLUDED.status,
                creative_type   = EXCLUDED.creative_type,
                headline        = EXCLUDED.headline,
                body            = EXCLUDED.body,
                image_url       = EXCLUDED.image_url,
                thumbnail_url   = EXCLUDED.thumbnail_url,
                call_to_action  = EXCLUDED.call_to_action,
                destination_url = EXCLUDED.destination_url,
                synced_at       = NOW(),
                updated_at      = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':ad_set_id'       => $adSetId,
            ':platform_id'     => $data['platform_id'],
            ':name'            => $data['name'],
            ':status'          => $data['status'],
            ':creative_type'   => $data['creative_type'] ?? null,
            ':headline'        => $data['headline'] ?? null,
            ':body'            => $data['body'] ?? null,
            ':image_url'       => $data['image_url'] ?? null,
            ':thumbnail_url'   => $data['thumbnail_url'] ?? null,
            ':call_to_action'  => $data['call_to_action'] ?? null,
            ':destination_url' => $data['destination_url'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
