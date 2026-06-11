<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\WhatsAppInstanceRepository;
use App\Services\EvolutionApiService;
use App\Support\Auth;

class GlobalSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsRepository $settings,
        private readonly WhatsAppInstanceRepository $instanceRepo,
        private readonly EvolutionApiService        $evolution,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $allSettings = $this->settings->getMultiple([
            'evolution_api_url',
            'evolution_api_key',
            'evolution_enabled',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_from_address',
            'mail_from_name',
            'mail_encryption',
            'meta_app_id',
            'meta_app_secret',
            'ai_provider',
            'ai_model',
            'openai_api_key',
            'anthropic_api_key',
        ]);

        $instances = $this->instanceRepo->findAll();

        return $this->view('admin.settings.index', compact('allSettings', 'instances'));
    }

    public function save(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        $evolutionUrl  = trim((string) $request->post('evolution_api_url', ''));
        $evolutionKey  = trim((string) $request->post('evolution_api_key', ''));
        $evolutionEnabled = $request->post('evolution_enabled') ? '1' : '0';

        $mailHost       = trim((string) $request->post('mail_host', ''));
        $mailPort       = (string) $request->post('mail_port', '587');
        $mailUser       = trim((string) $request->post('mail_username', ''));
        $mailFrom       = trim((string) $request->post('mail_from_address', ''));
        $mailFromName   = trim((string) $request->post('mail_from_name', ''));
        $mailEncryption = (string) $request->post('mail_encryption', 'tls');

        $metaAppId     = trim((string) $request->post('meta_app_id', ''));
        $metaAppSecret = trim((string) $request->post('meta_app_secret', ''));
        $aiProvider    = trim((string) $request->post('ai_provider', 'openai'));
        $aiModel       = trim((string) $request->post('ai_model', ''));
        $openaiKey     = trim((string) $request->post('openai_api_key', ''));
        $anthropicKey  = trim((string) $request->post('anthropic_api_key', ''));

        $map = [
            'evolution_api_url'    => $evolutionUrl,
            'evolution_enabled'    => $evolutionEnabled,
            'mail_host'            => $mailHost,
            'mail_port'            => $mailPort,
            'mail_username'        => $mailUser,
            'mail_from_address'    => $mailFrom,
            'mail_from_name'       => $mailFromName,
            'mail_encryption'      => $mailEncryption,
            'meta_app_id'  => $metaAppId,
            'ai_provider'  => $aiProvider,
            'ai_model'     => $aiModel,
        ];

        // Só salva a API key se não for placeholder (campo mascarado)
        if (!empty($evolutionKey) && $evolutionKey !== '••••••••') {
            $map['evolution_api_key'] = $evolutionKey;
        }
        if (!empty($metaAppSecret) && $metaAppSecret !== '••••••••') {
            $map['meta_app_secret'] = $metaAppSecret;
        }
        if (!empty($openaiKey) && $openaiKey !== '••••••••') {
            $map['openai_api_key'] = $openaiKey;
        }
        if (!empty($anthropicKey) && $anthropicKey !== '••••••••') {
            $map['anthropic_api_key'] = $anthropicKey;
        }

        $this->settings->setMultiple($map);

        $this->withSuccess('Configurações globais salvas.');
        return $this->redirect('/admin/configuracoes');
    }

    public function testEvolution(Request $request): Response
    {
        Auth::requirePlatformAdmin();

        if (!$this->evolution->isConfigured()) {
            return Response::json(['ok' => false, 'error' => 'Credenciais não configuradas.']);
        }

        $creds = $this->evolution->getGlobalCredentials();
        $ch    = curl_init(rtrim($creds['api_url'], '/') . '/instance/fetchInstances');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $creds['api_key'], 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw  = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return Response::json(['ok' => $code >= 200 && $code < 300, 'http' => $code]);
    }
}
