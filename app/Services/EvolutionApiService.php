<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformSettingsRepository;
use App\Repositories\WhatsAppInstanceRepository;

/**
 * Cliente para a Evolution API v2 (WhatsApp Baileys).
 *
 * Credenciais (api_url + api_key) são globais, gerenciadas pelo platform admin.
 * Cada agência tem sua própria instância (instance_name único gerado automaticamente).
 *
 * Fluxo de criação:
 *   1. createInstance(agencyId)     → POST /instance/create + POST /webhook/set
 *   2. getQrCode(instanceName)      → GET  /instance/connect/{name}
 *   3. checkStatus(instance)        → GET  /instance/connectionState/{name}
 *   4. fetchInstanceInfo(name)      → GET  /instance/fetchInstances?instanceName={name}
 *   5. disconnect(instanceName)     → DELETE /instance/logout/{name}
 */
class EvolutionApiService
{
    public function __construct(
        private readonly PlatformSettingsRepository  $settings,
        private readonly WhatsAppInstanceRepository  $instanceRepo,
    ) {}

    // ── Global credentials ────────────────────────────────────────────────────

    public function getGlobalCredentials(): array
    {
        $settings = $this->settings->getMultiple(['evolution_api_url', 'evolution_api_key', 'evolution_enabled']);

        return [
            'api_url' => $settings['evolution_api_url'] ?? env('EVOLUTION_API_URL', ''),
            'api_key' => $settings['evolution_api_key'] ?? env('EVOLUTION_API_KEY', ''),
            'enabled' => ($settings['evolution_enabled'] ?? '1') !== '0',
        ];
    }

    public function isConfigured(): bool
    {
        $creds = $this->getGlobalCredentials();
        return $creds['enabled'] && !empty($creds['api_url']) && !empty($creds['api_key']);
    }

    // ── Instance lifecycle ────────────────────────────────────────────────────

    /**
     * Cria instância na Evolution API e registra no banco.
     * Retorna a instância criada ou array com 'error'.
     */
    public function createInstance(int $agencyId, string $agencySlug, string $appUrl): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Evolution API não configurada. Contate o administrador.'];
        }

        $existing = $this->instanceRepo->findByAgency($agencyId);
        if ($existing) {
            return ['ok' => false, 'error' => 'Esta agência já possui uma instância WhatsApp.'];
        }

        $creds        = $this->getGlobalCredentials();
        $instanceName = ($agencySlug ?: 'agency' . $agencyId) . '-yve';
        $webhookToken = bin2hex(random_bytes(32));
        $webhookUrl   = rtrim($appUrl, '/') . '/webhook/evolution/' . $webhookToken;

        // Registrar no banco ANTES de chamar a Evolution (para ter o ID)
        $instanceId = $this->instanceRepo->create([
            'agency_id'     => $agencyId,
            'name'          => 'Principal',
            'instance_name' => $instanceName,
            'webhook_token' => $webhookToken,
        ]);

        // Criar instância na Evolution API (SEM webhook na criação — causa 400)
        $createRes = $this->request('POST', $creds['api_url'], $creds['api_key'], '/instance/create', [
            'instanceName' => $instanceName,
            'integration'  => 'WHATSAPP-BAILEYS',
            'qrcode'       => true,
        ]);

        if (!$createRes['ok']) {
            $this->instanceRepo->delete($instanceId);
            return ['ok' => false, 'error' => 'Falha ao criar instância na Evolution API: ' . ($createRes['body']['message'] ?? $createRes['raw'])];
        }

        // Configurar webhook em chamada separada
        $this->configureWebhookForInstance($instanceName, $webhookUrl, $creds);

        return ['ok' => true, 'instance' => $this->instanceRepo->findByAgency($agencyId)];
    }

    /**
     * Obtém QR Code para exibição. Tenta base64, code e qrcode na resposta.
     */
    public function getQrCode(string $instanceName): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Evolution API não configurada.'];
        }
        $creds = $this->getGlobalCredentials();
        $res   = $this->request('GET', $creds['api_url'], $creds['api_key'], "/instance/connect/{$instanceName}");

        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'Falha ao obter QR Code.'];
        }

        $body       = $res['body'] ?? [];
        $qrCode     = $body['base64'] ?? $body['code'] ?? $body['qrcode'] ?? null;
        $pairingCode = $body['pairingCode'] ?? null;

        return ['ok' => true, 'qr_code' => $qrCode, 'pairing_code' => $pairingCode];
    }

    /**
     * Verifica o estado da conexão e atualiza o banco.
     */
    public function checkStatus(array $instance): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'status' => 'unconfigured'];
        }
        $creds = $this->getGlobalCredentials();
        $res   = $this->request('GET', $creds['api_url'], $creds['api_key'],
            "/instance/connectionState/{$instance['instance_name']}"
        );

        if (!$res['ok']) {
            return ['connected' => false, 'status' => 'error', 'error' => $res['raw']];
        }

        $body      = $res['body'] ?? [];
        $state     = $body['instance']['state'] ?? $body['state'] ?? 'unknown';
        $connected = in_array(strtolower($state), ['open', 'connected'], true);
        $status    = $connected ? 'connected' : ($state === 'close' ? 'disconnected' : 'pending');

        // Buscar número quando conectado
        $phone = null;
        if ($connected) {
            $info  = $this->fetchInstanceInfo($instance['instance_name'], $creds);
            $phone = $info['phone'] ?? null;
        }

        $this->instanceRepo->updateStatus($instance['id'], $status, $phone, $connected);

        return ['connected' => $connected, 'status' => $status, 'state' => $state, 'phone' => $phone];
    }

    /**
     * Desconecta a instância (logout na Evolution).
     */
    public function disconnect(string $instanceName): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Evolution API não configurada.'];
        }
        $creds = $this->getGlobalCredentials();
        $res   = $this->request('DELETE', $creds['api_url'], $creds['api_key'],
            "/instance/logout/{$instanceName}"
        );
        return ['ok' => $res['ok'], 'error' => $res['ok'] ? null : ($res['body']['message'] ?? $res['raw'])];
    }

    // ── Messaging ─────────────────────────────────────────────────────────────

    public function sendText(int $agencyId, string $phone, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Evolution API não configurada.'];
        }

        $instance = $this->instanceRepo->findByAgency($agencyId);
        if (!$instance || $instance['status'] !== 'connected') {
            return ['success' => false, 'error' => 'Instância WhatsApp não conectada.'];
        }

        $creds = $this->getGlobalCredentials();
        $phone = $this->normalizePhone($phone);

        $res = $this->request('POST', $creds['api_url'], $creds['api_key'],
            "/message/sendText/{$instance['instance_name']}",
            ['number' => $phone, 'text' => $message, 'options' => ['delay' => 1000, 'presence' => 'composing']]
        );

        return ['success' => $res['ok'], 'response' => $res['body'], 'error' => $res['ok'] ? null : $res['raw']];
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function configureWebhookForInstance(string $instanceName, string $webhookUrl, array $creds): array
    {
        return $this->request('POST', $creds['api_url'], $creds['api_key'],
            "/webhook/set/{$instanceName}",
            [
                'webhook' => [
                    'enabled'  => true,
                    'url'      => $webhookUrl,
                    'byEvents' => false,
                    'base64'   => false,
                    'events'   => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
                ],
            ]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function fetchInstanceInfo(string $instanceName, array $creds): array
    {
        $res = $this->request('GET', $creds['api_url'], $creds['api_key'],
            "/instance/fetchInstances?instanceName={$instanceName}"
        );
        if (!$res['ok'] || empty($res['body'])) return [];

        $info = is_array($res['body']) ? ($res['body'][0] ?? $res['body']) : [];
        $ownerJid = $info['ownerJid'] ?? $info['owner'] ?? null;
        $phone = $ownerJid ? str_replace('@s.whatsapp.net', '', $ownerJid) : null;

        return ['phone' => $phone, 'raw' => $info];
    }

    /**
     * Executa qualquer requisição HTTP para a Evolution API.
     * Retorna ['ok' => bool, 'http' => int, 'body' => array|null, 'raw' => string]
     */
    private function request(
        string $method,
        string $baseUrl,
        string $apiKey,
        string $path,
        ?array $body = null
    ): array {
        $url = rtrim($baseUrl, '/') . $path;

        $headers = [
            'apikey: ' . $apiKey,
            'Accept: application/json',
        ];

        // Verificação TLS ligada por padrão; só desliga com EVOLUTION_SSL_VERIFY=false
        // (necessário apenas para servidores Evolution self-hosted com cert self-signed).
        $verifySsl = env('EVOLUTION_SSL_VERIFY', 'true') !== 'false';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $json = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw      = (string) curl_exec($ch);
        $httpCode = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        return [
            'ok'   => $httpCode >= 200 && $httpCode < 300,
            'http' => $httpCode,
            'body' => $decoded,
            'raw'  => $raw,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return $digits;
    }
}
