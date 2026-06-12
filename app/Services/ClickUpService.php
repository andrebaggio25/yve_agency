<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClickUpIntegrationRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Cliente para a ClickUp API v2.
 *
 * Autenticação: header "Authorization: {personal_token}" (sem "Bearer").
 * Saída  (YVE → ClickUp): pushTask() — cria ou atualiza tarefa.
 * Entrada (ClickUp → YVE): verificação HMAC feita no ClickUpWebhookController.
 */
class ClickUpService
{
    private const BASE_URL = 'https://api.clickup.com/api/v2';

    private static array $priorityMap = [
        'urgent' => 1,
        'high'   => 2,
        'medium' => 3,
        'low'    => 4,
    ];

    public function __construct(
        private readonly ClickUpIntegrationRepository $integrationRepo,
    ) {}

    // ── Configuração ──────────────────────────────────────────────────────────

    public function getIntegration(int $agencyId): ?array
    {
        $i = $this->integrationRepo->findByAgency($agencyId);
        return ($i && ($i['status'] ?? '') === 'active') ? $i : null;
    }

    public function isConfigured(int $agencyId): bool
    {
        return $this->getIntegration($agencyId) !== null;
    }

    // ── Saída: YVE → ClickUp ─────────────────────────────────────────────────

    /**
     * Cria ou atualiza uma tarefa no ClickUp.
     * Retorna ['ok' => bool, 'external_id' => string|null, 'error' => string|null].
     */
    public function pushTask(int $agencyId, array $task): array
    {
        $integration = $this->getIntegration($agencyId);
        if (!$integration) {
            return ['ok' => false, 'error' => 'ClickUp não configurado para esta agência'];
        }

        $payload = $this->buildPayload($task, $integration);
        $externalId = $task['external_id'] ?? null;

        try {
            if ($externalId) {
                $data = $this->request('PUT', "/task/{$externalId}", $integration['api_token'], $payload);
                return ['ok' => true, 'external_id' => $data['id'] ?? $externalId];
            } else {
                $listId = $integration['default_list_id'];
                $data   = $this->request('POST', "/list/{$listId}/task", $integration['api_token'], $payload);
                return ['ok' => true, 'external_id' => $data['id'] ?? null];
            }
        } catch (GuzzleException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Registra webhook na ClickUp API para receber atualizações de volta.
     * Retorna o webhook_id gerado pelo ClickUp.
     */
    public function registerWebhook(int $agencyId, string $appUrl): array
    {
        $integration = $this->getIntegration($agencyId);
        if (!$integration) {
            return ['ok' => false, 'error' => 'Integração não encontrada'];
        }

        $workspaceId = $integration['workspace_id'] ?? '';
        if (empty($workspaceId)) {
            return ['ok' => false, 'error' => 'Workspace ID não configurado'];
        }

        $endpoint  = rtrim($appUrl, '/') . '/webhook/clickup/' . $integration['webhook_token'];
        $secret    = $integration['webhook_token'];

        try {
            $data = $this->request('POST', "/team/{$workspaceId}/webhook", $integration['api_token'], [
                'endpoint' => $endpoint,
                'events'   => ['taskStatusUpdated', 'taskUpdated'],
                'secret'   => $secret,
            ]);
            $webhookId = $data['id'] ?? ($data['webhook']['id'] ?? null);
            if ($webhookId) {
                $this->integrationRepo->updateWebhookId($agencyId, (string) $webhookId);
            }
            return ['ok' => true, 'webhook_id' => $webhookId];
        } catch (GuzzleException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Mapeamento ────────────────────────────────────────────────────────────

    public function mapStatus(string $yveStatus, array $integration): string
    {
        $map = $integration['status_map'] ?? [];
        if (is_string($map)) {
            $map = json_decode($map, true) ?? [];
        }
        return $map[$yveStatus] ?? $yveStatus;
    }

    /** Converte status ClickUp → YVE usando o mapa invertido */
    public function reverseMapStatus(string $clickupStatus, array $integration): ?string
    {
        $map = $integration['status_map'] ?? [];
        if (is_string($map)) {
            $map = json_decode($map, true) ?? [];
        }
        $lower = strtolower($clickupStatus);
        foreach ($map as $yve => $cu) {
            if (strtolower((string) $cu) === $lower) {
                return $yve;
            }
        }
        return null;
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function buildPayload(array $task, array $integration): array
    {
        $payload = [
            'name'   => $task['title'],
            'status' => $this->mapStatus($task['status'] ?? 'todo', $integration),
        ];

        if (!empty($task['description'])) {
            $payload['description'] = $task['description'];
        }
        if (!empty($task['due_date'])) {
            $payload['due_date']      = strtotime($task['due_date']) * 1000; // ms
            $payload['due_date_time'] = false;
        }
        if (!empty($task['priority']) && isset(self::$priorityMap[$task['priority']])) {
            $payload['priority'] = self::$priorityMap[$task['priority']];
        }

        return $payload;
    }

    private function request(string $method, string $path, string $token, array $json = []): array
    {
        $client = new Client(['base_uri' => self::BASE_URL, 'timeout' => 10]);

        $options = [
            'headers' => [
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ],
        ];
        if (!empty($json)) {
            $options['json'] = $json;
        }

        $response = $client->request($method, $path, $options);
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
