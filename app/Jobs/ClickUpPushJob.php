<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\ClickUpIntegrationRepository;
use App\Repositories\TaskRepository;
use App\Services\ClickUpService;

/**
 * Enfileirado por TaskController após criar/atualizar uma tarefa.
 * Empurra a tarefa para o ClickUp se a integração estiver ativa.
 *
 * Payload: ['task_id' => int, 'agency_id' => int, 'action' => 'create|update']
 *
 * Anti-echo: se sync_source = 'clickup' e last_synced_at < 30s, a tarefa foi
 * atualizada pelo webhook do ClickUp — pular o push para evitar loop.
 */
class ClickUpPushJob
{
    public function handle(array $data): void
    {
        $taskId   = (int) ($data['task_id']   ?? 0);
        $agencyId = (int) ($data['agency_id'] ?? 0);
        if ($taskId <= 0 || $agencyId <= 0) return;

        $taskRepo    = new TaskRepository();
        $clickupRepo = new ClickUpIntegrationRepository();
        $clickup     = Container::getInstance()->make(ClickUpService::class);

        $task = $taskRepo->findByIdAndAgency($taskId, $agencyId);
        if (!$task) return;

        // Anti-echo: update veio do ClickUp há menos de 30s — não reenviar
        if (
            ($task['sync_source'] ?? '') === 'clickup' &&
            !empty($task['last_synced_at']) &&
            strtotime($task['last_synced_at']) > time() - 30
        ) {
            return;
        }

        $result = $clickup->pushTask($agencyId, $task);

        if ($result['ok'] && !empty($result['external_id'])) {
            $clickupRepo->updateExternalId($taskId, (string) $result['external_id']);
        }
    }
}
