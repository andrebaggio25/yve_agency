<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\WhatsAppInstanceRepository;
use App\Services\EvolutionApiService;
use App\Support\Auth;

class WhatsAppController extends Controller
{
    public function __construct(
        private readonly WhatsAppInstanceRepository $instanceRepo,
        private readonly EvolutionApiService        $evolution,
        private readonly PlatformSettingsRepository $settings,
    ) {}

    // ── Settings page ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId     = (int) Auth::agencyId();
        $instance     = $this->instanceRepo->findByAgency($agencyId);
        $globalOk     = $this->evolution->isConfigured();

        // Estado da conexão mais recente (não chama a API aqui para não travar o carregamento)
        return $this->view('settings.whatsapp', compact('instance', 'globalOk'));
    }

    // ── Ativar (criar instância) ───────────────────────────────────────────────

    public function activate(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId = (int) Auth::agencyId();

        // Buscar slug da agência
        $agency = $this->fetchAgency($agencyId);
        $slug   = $agency['slug'] ?? 'agency' . $agencyId;
        $appUrl = env('APP_URL', 'http://localhost:8000');

        $result = $this->evolution->createInstance($agencyId, $slug, $appUrl);

        if (!$result['ok']) {
            return Response::json(['ok' => false, 'error' => $result['error']]);
        }

        return Response::json(['ok' => true]);
    }

    // ── QR Code ───────────────────────────────────────────────────────────────

    public function qrCode(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId = (int) Auth::agencyId();
        $instance = $this->instanceRepo->findByAgency($agencyId);

        if (!$instance) {
            return Response::json(['ok' => false, 'error' => 'Instância não encontrada.']);
        }

        $result = $this->evolution->getQrCode($instance['instance_name']);
        return Response::json($result);
    }

    // ── Verificar status (polling) ────────────────────────────────────────────

    public function checkStatus(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId = (int) Auth::agencyId();
        $instance = $this->instanceRepo->findByAgency($agencyId);

        if (!$instance) {
            return Response::json(['connected' => false, 'status' => 'no_instance']);
        }

        $result = $this->evolution->checkStatus($instance);
        return Response::json($result);
    }

    // ── Desconectar ───────────────────────────────────────────────────────────

    public function disconnect(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId = (int) Auth::agencyId();
        $instance = $this->instanceRepo->findByAgency($agencyId);

        if (!$instance) {
            return Response::json(['ok' => false, 'error' => 'Instância não encontrada.']);
        }

        $result = $this->evolution->disconnect($instance['instance_name']);

        if ($result['ok']) {
            $this->instanceRepo->updateStatus($instance['id'], 'disconnected', null, false);
        }

        return Response::json($result);
    }

    // ── Configurar webhook manualmente ────────────────────────────────────────

    public function configureWebhook(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agencyId = (int) Auth::agencyId();
        $instance = $this->instanceRepo->findByAgency($agencyId);

        if (!$instance) {
            return Response::json(['ok' => false, 'error' => 'Instância não encontrada.']);
        }

        $appUrl     = env('APP_URL', 'http://localhost:8000');
        $webhookUrl = rtrim($appUrl, '/') . '/webhook/evolution/' . $instance['webhook_token'];
        $creds      = $this->evolution->getGlobalCredentials();

        $result = $this->evolution->configureWebhookForInstance(
            $instance['instance_name'],
            $webhookUrl,
            $creds
        );

        return Response::json(['ok' => $result['ok'], 'webhook_url' => $webhookUrl]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetchAgency(int $id): ?array
    {
        $stmt = \App\Core\Database::connection()->prepare("SELECT * FROM agencies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
