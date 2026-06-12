<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClickUpIntegrationRepository;
use App\Repositories\TaskRepository;
use App\Services\ClickUpService;

/**
 * Recebe eventos de atualização de tarefas vindos do ClickUp.
 *
 * URL:    POST /webhook/clickup/{token}
 * Auth:   HMAC-SHA256(webhook_token, raw_body) == X-Signature header
 * Anti-eco: marca sync_source='clickup' para evitar loop com o PushJob.
 */
class ClickUpWebhookController extends Controller
{
    public function __construct(
        private readonly ClickUpIntegrationRepository $integrationRepo,
        private readonly TaskRepository               $taskRepo,
        private readonly ClickUpService               $clickup,
    ) {}

    public function handle(Request $request): Response
    {
        $token = (string) $request->param('token');
        $integration = $this->integrationRepo->findByToken($token);

        if (!$integration || ($integration['status'] ?? '') !== 'active') {
            return Response::json(['error' => 'Not found'], 404);
        }

        // Verificar assinatura HMAC
        $rawBody   = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $expected  = hash_hmac('sha256', $rawBody, $integration['webhook_token']);

        if (!hash_equals($expected, $signature)) {
            return Response::json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($rawBody, true) ?? [];
        $this->processEvent($event, $integration);

        return Response::json(['ok' => true]);
    }

    private function processEvent(array $event, array $integration): void
    {
        $eventType = $event['event'] ?? '';

        // Eventos suportados: taskStatusUpdated, taskUpdated
        if (!in_array($eventType, ['taskStatusUpdated', 'taskUpdated'], true)) {
            return;
        }

        $clickupTaskId = $event['task_id'] ?? null;
        if (!$clickupTaskId) return;

        $agencyId = (int) $integration['agency_id'];

        // Encontrar tarefa pelo external_id
        $task = $this->taskRepo->findByExternalId((string) $clickupTaskId, $agencyId);
        if (!$task) return;

        $updates = ['sync_source' => 'clickup', 'last_synced_at' => date('Y-m-d H:i:s')];

        // Mapear status do ClickUp → YVE
        $clickupStatus = $event['history_items'][0]['after']['status'] ?? null;
        if ($clickupStatus) {
            $yveStatus = $this->clickup->reverseMapStatus((string) $clickupStatus, $integration);
            if ($yveStatus) {
                $updates['status'] = $yveStatus;
            }
        }

        // Nome da tarefa alterado
        $newName = $event['history_items'][0]['after']['name'] ?? null;
        if ($newName) {
            $updates['title'] = $newName;
        }

        $this->taskRepo->syncFromClickUp((int) $task['id'], $agencyId, $updates);
    }
}
