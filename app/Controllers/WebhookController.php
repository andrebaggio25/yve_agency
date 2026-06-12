<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\WhatsAppInstanceRepository;
use App\Support\ActivityLogger;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppInstanceRepository $instanceRepo,
    ) {}

    /**
     * Recebe eventos da Evolution API.
     * SEMPRE responde 200 para evitar reenvios em loop.
     */
    public function evolution(Request $request): Response
    {
        $token    = (string) $request->param('token');
        $instance = $this->instanceRepo->findByWebhookToken($token);

        if (!$instance) {
            // Token inválido — logar e responder 200 mesmo assim
            return Response::json(['received' => true]);
        }

        $body  = json_decode(\App\Core\Request::rawInput(), true) ?? [];
        $event = $body['event'] ?? $body['type'] ?? '';

        match ($event) {
            'CONNECTION_UPDATE' => $this->handleConnectionUpdate($instance, $body),
            'QRCODE_UPDATED'    => null, // ignorar — frontend já faz polling
            default             => null,
        };

        return Response::json(['received' => true]);
    }

    // ── Event handlers ────────────────────────────────────────────────────────

    private function handleConnectionUpdate(array $instance, array $body): void
    {
        $data  = $body['data'] ?? $body;
        $state = $data['state'] ?? $data['instance']['state'] ?? 'unknown';

        $connected = in_array(strtolower($state), ['open', 'connected'], true);
        $status    = $connected ? 'connected' : ($state === 'close' ? 'disconnected' : 'pending');

        $phone = null;
        if ($connected) {
            $ownerJid = $data['instance']['ownerJid'] ?? $data['ownerJid'] ?? null;
            $phone    = $ownerJid ? str_replace('@s.whatsapp.net', '', $ownerJid) : null;
        }

        $this->instanceRepo->updateStatus($instance['id'], $status, $phone, $connected);

        ActivityLogger::log('whatsapp_connection_update', 'whatsapp', null, null, [
            'agency_id' => $instance['agency_id'],
            'status'    => $status,
            'phone'     => $phone,
        ]);
    }
}
