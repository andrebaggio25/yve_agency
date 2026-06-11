<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class OrganicAccountRepository extends Repository
{
    protected string $table = 'organic_accounts';

    public function listByAgency(int $agencyId): array
    {
        return $this->all("
            SELECT a.*, c.name AS client_name
            FROM organic_accounts a
            LEFT JOIN clients c ON c.id = a.client_id
            WHERE a.agency_id = :aid
            ORDER BY a.name
        ", [':aid' => $agencyId]);
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT a.*, c.name AS client_name
            FROM organic_accounts a
            LEFT JOIN clients c ON c.id = a.client_id
            WHERE a.id = :id AND a.agency_id = :aid
            LIMIT 1
        ", [':id' => $id, ':aid' => $agencyId]);
    }

    public function findByClient(int $clientId, int $agencyId): array
    {
        return $this->all("
            SELECT * FROM organic_accounts
            WHERE client_id = :client_id AND agency_id = :agency_id AND status = 'active'
            ORDER BY name
        ", [':client_id' => $clientId, ':agency_id' => $agencyId]);
    }

    public function findAllActive(): array
    {
        return $this->all(
            "SELECT * FROM organic_accounts WHERE status = 'active' ORDER BY agency_id, id"
        );
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO organic_accounts
                (agency_id, client_id, platform, platform_page_id, instagram_user_id,
                 name, username, profile_picture_url, biography, website,
                 access_token, token_expires_at, followers_count, following_count,
                 media_count, status, sync_days_back, created_by, created_at, updated_at)
            VALUES
                (:agency_id, :client_id, :platform, :platform_page_id, :instagram_user_id,
                 :name, :username, :pic, :bio, :website,
                 :access_token, :token_expires_at, :followers, :following,
                 :media_count, 'active', :sync_days_back, :created_by, NOW(), NOW())
            ON CONFLICT (agency_id, platform, platform_page_id)
            DO UPDATE SET
                name                = EXCLUDED.name,
                username            = EXCLUDED.username,
                profile_picture_url = EXCLUDED.profile_picture_url,
                biography           = EXCLUDED.biography,
                access_token        = EXCLUDED.access_token,
                token_expires_at    = EXCLUDED.token_expires_at,
                followers_count     = EXCLUDED.followers_count,
                following_count     = EXCLUDED.following_count,
                media_count         = EXCLUDED.media_count,
                status              = 'active',
                updated_at          = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'         => $data['agency_id'],
            ':client_id'         => $data['client_id'] ?: null,
            ':platform'          => $data['platform'],
            ':platform_page_id'  => $data['platform_page_id'],
            ':instagram_user_id' => $data['instagram_user_id'] ?? null,
            ':name'              => $data['name'],
            ':username'          => $data['username'] ?? null,
            ':pic'               => $data['profile_picture_url'] ?? null,
            ':bio'               => $data['biography'] ?? null,
            ':website'           => $data['website'] ?? null,
            ':access_token'      => $data['access_token'],
            ':token_expires_at'  => $data['token_expires_at'] ?? null,
            ':followers'         => $data['followers_count'] ?? 0,
            ':following'         => $data['following_count'] ?? 0,
            ':media_count'       => $data['media_count'] ?? 0,
            ':sync_days_back'    => $data['sync_days_back'] ?? 30,
            ':created_by'        => $data['created_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateSyncedAt(int $id, array $counters = []): void
    {
        $this->pdo->prepare("
            UPDATE organic_accounts SET
                last_synced_at  = NOW(),
                followers_count = COALESCE(:followers, followers_count),
                following_count = COALESCE(:following, following_count),
                media_count     = COALESCE(:media, media_count),
                updated_at      = NOW()
            WHERE id = :id
        ")->execute([
            ':followers' => $counters['followers_count'] ?? null,
            ':following' => $counters['following_count'] ?? null,
            ':media'     => $counters['media_count']     ?? null,
            ':id'        => $id,
        ]);
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM organic_accounts WHERE id = :id AND agency_id = :aid"
        );
        $stmt->execute([':id' => $id, ':aid' => $agencyId]);
        return $stmt->rowCount() > 0;
    }
}
