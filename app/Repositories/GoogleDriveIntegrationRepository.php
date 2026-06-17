<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class GoogleDriveIntegrationRepository extends Repository
{
    protected string $table = 'google_drive_integrations';

    public function findByAgency(int $agencyId): ?array
    {
        return $this->first(
            "SELECT * FROM google_drive_integrations WHERE agency_id = :a",
            [':a' => $agencyId]
        );
    }

    /**
     * Cria/atualiza a conexão. Preserva o refresh_token quando um novo não vem
     * (o Google só devolve refresh_token no primeiro consentimento).
     */
    public function upsert(int $agencyId, array $data): void
    {
        $this->pdo->prepare("
            INSERT INTO google_drive_integrations
                (agency_id, access_token, refresh_token, token_expires_at, connected_email, status, created_at, updated_at)
            VALUES
                (:agency_id, :access_token, :refresh_token, :token_expires_at, :connected_email, 'active', NOW(), NOW())
            ON CONFLICT (agency_id) DO UPDATE SET
                access_token     = EXCLUDED.access_token,
                refresh_token    = COALESCE(NULLIF(EXCLUDED.refresh_token, ''), google_drive_integrations.refresh_token),
                token_expires_at = EXCLUDED.token_expires_at,
                connected_email  = COALESCE(EXCLUDED.connected_email, google_drive_integrations.connected_email),
                status           = 'active',
                updated_at       = NOW()
        ")->execute([
            ':agency_id'        => $agencyId,
            ':access_token'     => $data['access_token'] ?? null,
            ':refresh_token'    => $data['refresh_token'] ?? '',
            ':token_expires_at' => $data['token_expires_at'] ?? null,
            ':connected_email'  => $data['connected_email'] ?? null,
        ]);
    }

    /** Atualiza só o access token (usado no refresh). */
    public function updateAccessToken(int $agencyId, string $accessToken, ?string $expiresAt): void
    {
        $this->pdo->prepare("
            UPDATE google_drive_integrations
            SET access_token = :t, token_expires_at = :e, updated_at = NOW()
            WHERE agency_id = :a
        ")->execute([':t' => $accessToken, ':e' => $expiresAt, ':a' => $agencyId]);
    }

    public function setRootFolder(int $agencyId, string $folderId): void
    {
        $this->pdo->prepare(
            "UPDATE google_drive_integrations SET root_folder_id = :f, updated_at = NOW() WHERE agency_id = :a"
        )->execute([':f' => $folderId, ':a' => $agencyId]);
    }

    public function deactivate(int $agencyId): void
    {
        $this->pdo->prepare(
            "UPDATE google_drive_integrations SET status = 'inactive', updated_at = NOW() WHERE agency_id = :a"
        )->execute([':a' => $agencyId]);
    }
}
