<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\OrganicAccountRepository;
use App\Repositories\OrganicMetricsRepository;
use App\Repositories\ClientRepository;
use App\Services\MetaOrganicService;
use App\Services\OrganicSyncService;
use App\Services\BillingService;

class OrganicController extends Controller
{
    public function __construct(
        private readonly OrganicAccountRepository $accountRepo,
        private readonly OrganicMetricsRepository $metricsRepo,
        private readonly ClientRepository         $clientRepo,
        private readonly MetaOrganicService       $metaService,
        private readonly OrganicSyncService       $syncService,
        private readonly BillingService           $billing,
    ) {}

    // ---------------------------------------------------------------- dashboard

    public function index(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $agencyId = Auth::agencyId();

        $since = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until = $request->query('until', date('Y-m-d'));

        $accounts = $this->accountRepo->listByAgency($agencyId);
        $overview = $this->metricsRepo->summaryForAgency($agencyId, $since, $until);

        // KPIs totais
        $totals = [
            'followers'   => array_sum(array_column($accounts, 'followers_count')),
            'posts'       => array_sum(array_column($overview, 'total_posts')),
            'reach'       => array_sum(array_column($overview, 'total_reach')),
            'impressions' => array_sum(array_column($overview, 'total_impressions')),
            'likes'       => array_sum(array_column($overview, 'total_likes')),
        ];
        $totals['avg_er'] = !empty($overview)
            ? array_sum(array_column($overview, 'avg_engagement_rate')) / count($overview)
            : 0;

        return $this->view('organico.index', compact('accounts', 'overview', 'totals', 'since', 'until'));
    }

    // ---------------------------------------------------------- account detail

    public function account(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $id       = (int) $request->param('id');
        $agencyId = Auth::agencyId();

        $account = $this->accountRepo->findById($id, $agencyId);
        if (!$account) {
            return Response::view('errors.404', [], 404);
        }

        $since  = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until  = $request->query('until', date('Y-m-d'));
        $sortBy = $request->query('sort', 'date');

        $summary    = $this->metricsRepo->summaryForAccount($id, $since, $until);
        $topPosts   = $this->metricsRepo->topPosts($id, $since, $until, $sortBy, 12);
        $allPosts   = $this->metricsRepo->listPosts($id, ['since' => $since, 'until' => $until, 'sort' => $sortBy]);
        $dailyChart = $this->metricsRepo->dailyForChart($id, $since, $until);

        return $this->view('organico.account', compact(
            'account', 'summary', 'topPosts', 'allPosts', 'dailyChart', 'since', 'until', 'sortBy'
        ));
    }

    // --------------------------------------------------------- connect account

    public function connectForm(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $clients = $this->clientRepo->findByAgency(Auth::agencyId());
        return $this->view('organico.connect', compact('clients'));
    }

    public function connect(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $agencyId = Auth::agencyId();

        if (!$this->billing->checkLimit((int) $agencyId, 'organic_accounts')) {
            $this->withError('Limite de contas orgânicas atingido. Faça upgrade para conectar mais.');
            return $this->redirect('/organico/conectar');
        }

        $platform = $request->post('platform', 'instagram');
        $token    = trim((string) $request->post('access_token', ''));
        $pageId   = trim((string) $request->post('platform_page_id', ''));
        $clientId = (int) $request->post('client_id', 0);

        if (!$token || !$pageId) {
            $this->withError('Token e ID da página são obrigatórios.');
            return $this->redirect('/organico/conectar');
        }

        try {
            // Busca page token de longa duração
            $pageTokenData = $this->metaService->fetchPageToken($token, $pageId);
            $pageToken     = $pageTokenData['access_token'];

            // Busca info da página
            $pageInfo  = $this->metaService->fetchPageInfo($pageToken, $pageId);
            $igData    = [];
            $igUserId  = null;

            if ($platform === 'instagram') {
                $igUserId = $pageInfo['instagram_business_account']['id'] ?? null;
                if (!$igUserId) {
                    $this->withError('Esta página não tem uma conta Instagram Business vinculada.');
                    return $this->redirect('/organico/conectar');
                }
                $igData = $this->metaService->fetchInstagramInfo($pageToken, $igUserId);
            }

            $name     = $platform === 'instagram' ? ($igData['name'] ?? $pageInfo['name'] ?? "Conta {$pageId}") : ($pageInfo['name'] ?? "Página {$pageId}");
            $username = $platform === 'instagram' ? ($igData['username'] ?? null) : null;

            $this->accountRepo->create([
                'agency_id'           => $agencyId,
                'client_id'           => $clientId ?: null,
                'platform'            => $platform,
                'platform_page_id'    => $pageId,
                'instagram_user_id'   => $igUserId,
                'name'                => $name,
                'username'            => $username,
                'profile_picture_url' => $igData['profile_picture_url'] ?? ($pageInfo['picture']['data']['url'] ?? null),
                'biography'           => $igData['biography'] ?? $pageInfo['about'] ?? null,
                'website'             => $igData['website']   ?? $pageInfo['website'] ?? null,
                'access_token'        => $pageToken,
                'followers_count'     => $igData['followers_count'] ?? $pageInfo['fan_count'] ?? 0,
                'following_count'     => $igData['follows_count']   ?? 0,
                'media_count'         => $igData['media_count']     ?? 0,
                'sync_days_back'      => (int) $request->post('sync_days_back', 30),
                'created_by'          => Auth::id(),
            ]);

            $this->withSuccess("Conta @{$username} conectada com sucesso!");
        } catch (\Throwable $e) {
            $this->withError('Erro ao conectar: ' . $e->getMessage());
            return $this->redirect('/organico/conectar');
        }

        return $this->redirect('/organico/contas');
    }

    // ----------------------------------------------------------------- accounts

    public function accounts(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $accounts = $this->accountRepo->listByAgency(Auth::agencyId());
        return $this->view('organico.accounts', compact('accounts'));
    }

    public function syncOne(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $id      = (int) $request->param('id');
        $account = $this->accountRepo->findById($id, Auth::agencyId());

        if (!$account) {
            $this->withError('Conta não encontrada.');
            return $this->redirect('/organico/contas');
        }

        try {
            $stats = $this->syncService->syncAccount($account);
            $this->withSuccess("Sincronizado: {$stats['posts']} posts.");
        } catch (\Throwable $e) {
            $this->withError('Erro: ' . $e->getMessage());
        }

        return $this->redirect('/organico/contas');
    }

    public function destroy(Request $request): Response
    {
        Auth::requirePermission('organic_metrics.view');
        $id = (int) $request->param('id');
        $this->accountRepo->deleteById($id, Auth::agencyId());
        $this->withSuccess('Conta removida.');
        return $this->redirect('/organico/contas');
    }
}
