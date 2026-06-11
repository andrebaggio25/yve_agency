<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\AdAccountRepository;
use App\Repositories\ClientRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\MetaAdsService;
use App\Services\AdsSyncService;
use App\Services\BillingService;

class AdsAccountController extends Controller
{
    public function __construct(
        private readonly AdAccountRepository       $repo,
        private readonly ClientRepository          $clientRepo,
        private readonly MetaAdsService            $meta,
        private readonly AdsSyncService            $sync,
        private readonly BillingService            $billing,
        private readonly PlatformSettingsRepository $platformSettings,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $accounts = $this->repo->listByAgency(Auth::agencyId());
        return $this->view('trafego.accounts', compact('accounts'));
    }

    public function create(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $clients          = $this->clientRepo->findByAgency(Auth::agencyId());
        $metaAppConfigured = (bool) $this->platformSettings->get('meta_app_id', '');
        return $this->view('trafego.connect', compact('clients', 'metaAppConfigured'));
    }

    // ───────────────────────────────────────────── Meta OAuth connect
    public function oauthStart(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');

        $appId = $this->platformSettings->get('meta_app_id', '');
        if (!$appId) {
            $this->withError('Meta App ID não configurado. Peça ao administrador da plataforma para configurar nas Configurações do Admin.');
            return $this->redirect('/trafego/contas/nova');
        }

        $state    = bin2hex(random_bytes(16));
        $_SESSION['meta_oauth_state'] = $state;

        $clientId   = (int) $request->query('client_id', 0);
        $_SESSION['meta_oauth_client_id'] = $clientId;

        $appUrl     = rtrim(env('APP_URL', 'http://localhost'), '/');
        $callbackUrl = urlencode("{$appUrl}/trafego/contas/oauth/callback");

        $scopes = urlencode('ads_read,ads_management,business_management');
        $url    = "https://www.facebook.com/dialog/oauth?client_id={$appId}&redirect_uri={$callbackUrl}&scope={$scopes}&state={$state}&response_type=code";

        return Response::redirect($url);
    }

    public function oauthCallback(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');

        $code  = $request->query('code', '');
        $state = $request->query('state', '');
        $error = $request->query('error', '');

        if ($error) {
            $this->withError('Autorização cancelada pelo Facebook.');
            return $this->redirect('/trafego/contas/nova');
        }

        if (!$code || $state !== ($_SESSION['meta_oauth_state'] ?? '')) {
            $this->withError('Requisição OAuth inválida. Tente novamente.');
            return $this->redirect('/trafego/contas/nova');
        }

        unset($_SESSION['meta_oauth_state']);
        $clientId = (int) ($_SESSION['meta_oauth_client_id'] ?? 0);
        unset($_SESSION['meta_oauth_client_id']);

        if (!$this->billing->checkLimit((int) Auth::agencyId(), 'meta_accounts')) {
            $this->withError('Limite de contas Meta Ads atingido. Faça upgrade para conectar mais.');
            return $this->redirect('/trafego/contas');
        }

        [$appId, $appSecret] = $this->platformSettings->getMultiple(['meta_app_id', 'meta_app_secret'])
            ? array_values($this->platformSettings->getMultiple(['meta_app_id', 'meta_app_secret']))
            : ['', ''];

        $appUrl      = rtrim(env('APP_URL', 'http://localhost'), '/');
        $callbackUrl = "{$appUrl}/trafego/contas/oauth/callback";

        // Trocar code por short-lived token
        $tokenUrl = "https://graph.facebook.com/v21.0/oauth/access_token";
        $resp = file_get_contents("{$tokenUrl}?client_id={$appId}&redirect_uri=" . urlencode($callbackUrl) . "&client_secret={$appSecret}&code={$code}");
        $tokenData = json_decode($resp ?: '{}', true);

        if (empty($tokenData['access_token'])) {
            $this->withError('Falha ao obter token de acesso. Tente novamente.');
            return $this->redirect('/trafego/contas/nova');
        }

        $shortToken = $tokenData['access_token'];

        // Trocar por token de longa duração
        try {
            $longTokenData = $this->meta->exchangeForLongLivedToken($shortToken);
            $finalToken    = $longTokenData['access_token'] ?? $shortToken;
            $expiresAt     = isset($longTokenData['expires_in'])
                ? date('Y-m-d H:i:s', time() + (int) $longTokenData['expires_in'])
                : null;
        } catch (\Throwable) {
            $finalToken = $shortToken;
            $expiresAt  = null;
        }

        // Buscar contas de anúncios vinculadas ao usuário
        $accountsResp = file_get_contents("https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,currency,account_status&access_token={$finalToken}");
        $accountsData = json_decode($accountsResp ?: '{}', true);
        $adAccounts   = $accountsData['data'] ?? [];

        if (empty($adAccounts)) {
            $this->withError('Nenhuma conta de anúncios encontrada para este usuário do Facebook.');
            return $this->redirect('/trafego/contas/nova');
        }

        // Salvar o token em sessão e redirecionar para seleção da conta
        $_SESSION['meta_oauth_token']     = $finalToken;
        $_SESSION['meta_oauth_expires_at'] = $expiresAt;
        $_SESSION['meta_oauth_accounts']  = $adAccounts;
        $_SESSION['meta_oauth_client_id'] = $clientId;

        return $this->view('trafego.oauth_select', [
            'accounts' => $adAccounts,
            'clientId' => $clientId,
            'clients'  => $this->clientRepo->findByAgency(Auth::agencyId()),
        ]);
    }

    public function oauthSave(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');

        $accountId  = trim((string) $request->post('account_id', ''));
        $accountName = trim((string) $request->post('account_name', ''));
        $currency   = trim((string) $request->post('currency', 'BRL'));
        $clientId   = (int) $request->post('client_id', 0);
        $syncDays   = (int) $request->post('sync_days_back', 30);

        $finalToken = $_SESSION['meta_oauth_token'] ?? '';
        $expiresAt  = $_SESSION['meta_oauth_expires_at'] ?? null;

        unset($_SESSION['meta_oauth_token'], $_SESSION['meta_oauth_expires_at'],
              $_SESSION['meta_oauth_accounts'], $_SESSION['meta_oauth_client_id']);

        if (!$finalToken || !$accountId) {
            $this->withError('Sessão OAuth expirada. Inicie o processo novamente.');
            return $this->redirect('/trafego/contas/nova');
        }

        // Strip 'act_' prefix
        $cleanId = ltrim($accountId, 'act_');

        $this->repo->create([
            'agency_id'           => Auth::agencyId(),
            'client_id'           => $clientId ?: null,
            'platform'            => 'meta',
            'platform_account_id' => $cleanId,
            'name'                => $accountName ?: "Conta {$cleanId}",
            'currency'            => $currency,
            'access_token'        => $finalToken,
            'token_type'          => 'user',
            'token_expires_at'    => $expiresAt,
            'sync_days_back'      => $syncDays,
            'created_by'          => Auth::id(),
        ]);

        $this->withSuccess('Conta Meta Ads conectada via OAuth com sucesso!');
        return $this->redirect('/trafego/contas');
    }

    // ───────────────────────────────────────────────────────────────
    public function store(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');

        if (!$this->billing->checkLimit((int) Auth::agencyId(), 'meta_accounts')) {
            $this->withError('Limite de contas Meta Ads atingido. Faça upgrade para conectar mais.');
            return $this->redirect('/trafego/contas/nova');
        }

        $agencyId  = Auth::agencyId();
        $token     = trim((string) $request->post('access_token', ''));
        $accountId = trim((string) $request->post('platform_account_id', ''));
        $clientId  = (int) $request->post('client_id', 0);

        if (!$token || !$accountId) {
            $this->withError('Token de acesso e ID da conta são obrigatórios.');
            return $this->redirect('/trafego/contas/nova');
        }

        try {
            $longToken  = $this->meta->exchangeForLongLivedToken($token);
            $finalToken = $longToken['access_token'] ?? $token;
            $expiresAt  = isset($longToken['expires_in'])
                ? date('Y-m-d H:i:s', time() + (int) $longToken['expires_in'])
                : null;
        } catch (\Throwable) {
            $finalToken = $token;
            $expiresAt  = null;
        }

        try {
            $info = $this->meta->fetchAccountInfo($finalToken, $accountId);
        } catch (\Throwable $e) {
            $this->withError('Não foi possível validar a conta: ' . $e->getMessage());
            return $this->redirect('/trafego/contas/nova');
        }

        $this->repo->create([
            'agency_id'           => $agencyId,
            'client_id'           => $clientId ?: null,
            'platform'            => 'meta',
            'platform_account_id' => $accountId,
            'name'                => $info['name'] ?? "Conta {$accountId}",
            'currency'            => $info['currency'] ?? 'BRL',
            'access_token'        => $finalToken,
            'token_type'          => 'user',
            'token_expires_at'    => $expiresAt,
            'sync_days_back'      => (int) $request->post('sync_days_back', 30),
            'created_by'          => Auth::id(),
        ]);

        $this->withSuccess('Conta conectada com sucesso!');
        return $this->redirect('/trafego/contas');
    }

    public function syncOne(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $id      = (int) $request->param('id');
        $account = $this->repo->findById($id, Auth::agencyId());

        if (!$account) {
            $this->withError('Conta não encontrada.');
            return $this->redirect('/trafego/contas');
        }

        try {
            $stats = $this->sync->syncAccount($account);
            $this->withSuccess("Sincronizado: {$stats['campaigns']} campanhas, {$stats['adsets']} conjuntos, {$stats['ads']} anúncios.");
        } catch (\Throwable $e) {
            $this->withError('Erro na sincronização: ' . $e->getMessage());
        }

        return $this->redirect('/trafego/contas');
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $id = (int) $request->param('id');
        $this->repo->deleteById($id, Auth::agencyId());
        $this->withSuccess('Conta removida.');
        return $this->redirect('/trafego/contas');
    }
}
