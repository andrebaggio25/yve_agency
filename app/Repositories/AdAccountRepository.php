<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdAccountRepository extends Repository
{
    protected string $table = 'ad_accounts';

    public function listByAgency(int $agencyId): array
    {
        return $this->all("
            SELECT a.*, c.name AS client_name
            FROM ad_accounts a
            LEFT JOIN clients c ON c.id = a.client_id
            WHERE a.agency_id = :aid
            ORDER BY a.name
        ", [':aid' => $agencyId]);
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first("
            SELECT a.*, c.name AS client_name
            FROM ad_accounts a
            LEFT JOIN clients c ON c.id = a.client_id
            WHERE a.id = :id AND a.agency_id = :aid
            LIMIT 1
        ", [':id' => $id, ':aid' => $agencyId]);
    }

    public function findByClient(int $clientId, int $agencyId): array
    {
        return $this->all("
            SELECT * FROM ad_accounts
            WHERE client_id = :client_id AND agency_id = :agency_id AND status = 'active'
            ORDER BY name
        ", [':client_id' => $clientId, ':agency_id' => $agencyId]);
    }

    public function findAllActive(): array
    {
        return $this->all(
            "SELECT * FROM ad_accounts WHERE status = 'active' ORDER BY agency_id, id"
        );
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_accounts
                (agency_id, client_id, platform, platform_account_id, name, currency,
                 access_token, token_type, token_expires_at, status, sync_days_back, created_by, created_at, updated_at)
            VALUES
                (:agency_id, :client_id, :platform, :platform_account_id, :name, :currency,
                 :access_token, :token_type, :token_expires_at, 'active', :sync_days_back, :created_by, NOW(), NOW())
            ON CONFLICT (agency_id, platform, platform_account_id)
            DO UPDATE SET
                name              = EXCLUDED.name,
                access_token      = EXCLUDED.access_token,
                token_type        = EXCLUDED.token_type,
                token_expires_at  = EXCLUDED.token_expires_at,
                status            = 'active',
                updated_at        = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'          => $data['agency_id'],
            ':client_id'          => $data['client_id'] ?: null,
            ':platform'           => $data['platform'] ?? 'meta',
            ':platform_account_id'=> $data['platform_account_id'],
            ':name'               => $data['name'],
            ':currency'           => $data['currency'] ?? 'BRL',
            ':access_token'       => $data['access_token'],
            ':token_type'         => $data['token_type'] ?? 'user',
            ':token_expires_at'   => $data['token_expires_at'] ?? null,
            ':sync_days_back'     => $data['sync_days_back'] ?? 30,
            ':created_by'         => $data['created_by'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateSyncedAt(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE ad_accounts SET last_synced_at = NOW(), updated_at = NOW() WHERE id = :id"
        )->execute([':id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->pdo->prepare(
            "UPDATE ad_accounts SET status = :status, updated_at = NOW() WHERE id = :id"
        )->execute([':status' => $status, ':id' => $id]);
    }

    public function deleteById(int $id, int $agencyId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM ad_accounts WHERE id = :id AND agency_id = :aid"
        );
        $stmt->execute([':id' => $id, ':aid' => $agencyId]);
        return $stmt->rowCount() > 0;
    }

    public function updateToken(int $id, string $token, ?string $expiresAt): void
    {
        $this->pdo->prepare("
            UPDATE ad_accounts SET access_token = :token, token_expires_at = :exp, updated_at = NOW()
            WHERE id = :id
        ")->execute([':token' => $token, ':exp' => $expiresAt, ':id' => $id]);
    }
}
