<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class ClickUpIntegrationRepository extends Repository
{
    protected string $table = 'clickup_integrations';

    public function findByAgency(int $agencyId): ?array
    {
        return $this->first(
            "SELECT * FROM clickup_integrations WHERE agency_id = :a",
            [':a' => $agencyId]
        );
    }

    public function findByToken(string $token): ?array
    {
        return $this->first(
            "SELECT * FROM clickup_integrations WHERE webhook_token = :t",
            [':t' => $token]
        );
    }

    public function upsert(int $agencyId, array $data): void
    {
        $statusMap = isset($data['status_map']) ? json_encode($data['status_map']) : null;

        $this->pdo->prepare("
            INSERT INTO clickup_integrations
                (agency_id, api_token, workspace_id, default_list_id, webhook_token, status_map, status, created_at, updated_at)
            VALUES
                (:agency_id, :api_token, :workspace_id, :default_list_id, :webhook_token,
                 COALESCE(:status_map::jsonb, '{\"todo\":\"to do\",\"in_progress\":\"in progress\",\"review\":\"review\",\"done\":\"complete\"}'::jsonb),
                 'active', NOW(), NOW())
            ON CONFLICT (agency_id) DO UPDATE SET
                api_token       = EXCLUDED.api_token,
                workspace_id    = EXCLUDED.workspace_id,
                default_list_id = EXCLUDED.default_list_id,
                status_map      = COALESCE(EXCLUDED.status_map, clickup_integrations.status_map),
                status          = 'active',
                updated_at      = NOW()
        ")->execute([
            ':agency_id'      => $agencyId,
            ':api_token'      => $data['api_token'],
            ':workspace_id'   => $data['workspace_id'] ?? null,
            ':default_list_id'=> $data['default_list_id'],
            ':webhook_token'  => $data['webhook_token'] ?? bin2hex(random_bytes(32)),
            ':status_map'     => $statusMap,
        ]);
    }

    public function updateWebhookId(int $agencyId, string $webhookId): void
    {
        $this->pdo->prepare(
            "UPDATE clickup_integrations SET webhook_id = :wid, updated_at = NOW() WHERE agency_id = :a"
        )->execute([':wid' => $webhookId, ':a' => $agencyId]);
    }

    public function deactivate(int $agencyId): void
    {
        $this->pdo->prepare(
            "UPDATE clickup_integrations SET status = 'inactive', updated_at = NOW() WHERE agency_id = :a"
        )->execute([':a' => $agencyId]);
    }

    public function updateExternalId(int $taskId, string $externalId): void
    {
        $this->pdo->prepare(
            "UPDATE tasks SET external_id = :eid, sync_source = 'yve', last_synced_at = NOW(), updated_at = NOW() WHERE id = :id"
        )->execute([':eid' => $externalId, ':id' => $taskId]);
    }
}
