<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\AdsActionRepository;
use App\Repositories\AdAccountRepository;
use App\Repositories\CampaignRepository;
use App\Services\AdsActionService;

class AdsActionController extends Controller
{
    public function __construct(
        private readonly AdsActionRepository $repo,
        private readonly AdAccountRepository $accountRepo,
        private readonly CampaignRepository  $campaignRepo,
        private readonly AdsActionService    $service,
    ) {}

    // ------------------------------------------------------------------ list

    public function index(Request $request): Response
    {
        Auth::requirePermission('ads_actions.view');
        $agencyId = Auth::agencyId();

        $filters = [
            'status'        => $request->query('status', ''),
            'ad_account_id' => $request->query('account_id', ''),
        ];

        $actions  = $this->repo->listByAgency($agencyId, $filters);
        $accounts = $this->accountRepo->listByAgency($agencyId);
        $pending  = $this->repo->countPending($agencyId);

        return $this->view('trafego.actions', compact('actions', 'accounts', 'filters', 'pending'));
    }

    // --------------------------------------------------------------- create manual

    public function create(Request $request): Response
    {
        Auth::requirePermission('ads_actions.request');
        $agencyId  = Auth::agencyId();
        $accounts  = $this->accountRepo->listByAgency($agencyId);
        $campaigns = [];

        $accountId = (int) $request->query('account_id', '0');
        if ($accountId) {
            $campaigns = $this->campaignRepo->listByAccount($accountId);
        }

        return $this->view('trafego.action_create', compact('accounts', 'campaigns', 'accountId'));
    }

    public function store(Request $request): Response
    {
        Auth::requirePermission('ads_actions.request');
        $agencyId = Auth::agencyId();

        try {
            $id = $this->service->createManual($agencyId, [
                'ad_account_id'  => $request->post('ad_account_id'),
                'campaign_id'    => $request->post('campaign_id'),
                'ad_set_id'      => $request->post('ad_set_id'),
                'action_type'    => $request->post('action_type'),
                'description'    => $request->post('description'),
                'justification'  => $request->post('justification'),
                'current_value'  => $request->post('current_value'),
                'proposed_value' => $request->post('proposed_value'),
            ]);
            $this->withSuccess('Ação criada e aguardando aprovação.');
            return $this->redirect('/trafego/acoes/' . $id);
        } catch (\Throwable $e) {
            $this->withError('Erro ao criar ação: ' . $e->getMessage());
            return $this->back();
        }
    }

    // ------------------------------------------------------------------ show

    public function show(Request $request): Response
    {
        Auth::requirePermission('ads_actions.view');
        $id     = (int) $request->param('id');
        $action = $this->repo->findById($id, Auth::agencyId());
        if (!$action) {
            return Response::view('errors.404', [], 404);
        }
        return $this->view('trafego.action_show', compact('action'));
    }

    // --------------------------------------------------------------- workflow

    public function approve(Request $request): Response
    {
        Auth::requirePermission('ads_actions.approve');
        $id = (int) $request->param('id');
        try {
            $this->service->approve($id, Auth::agencyId());
            $this->withSuccess('Ação aprovada.');
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
        }
        return $this->redirect('/trafego/acoes/' . $id);
    }

    public function reject(Request $request): Response
    {
        Auth::requirePermission('ads_actions.approve');
        $id = (int) $request->param('id');
        try {
            $this->service->reject($id, Auth::agencyId());
            $this->withSuccess('Ação rejeitada.');
        } catch (\Throwable $e) {
            $this->withError($e->getMessage());
        }
        return $this->redirect('/trafego/acoes/' . $id);
    }

    public function execute(Request $request): Response
    {
        Auth::requirePermission('ads_actions.execute');
        $id = (int) $request->param('id');
        try {
            $this->service->execute($id, Auth::agencyId());
            $this->withSuccess('Ação executada com sucesso via Meta Ads API.');
        } catch (\Throwable $e) {
            $this->withError('Falha na execução: ' . $e->getMessage());
        }
        return $this->redirect('/trafego/acoes/' . $id);
    }

    // -------------------------------------------------------- campaigns AJAX

    public function campaignsForAccount(Request $request): Response
    {
        Auth::requirePermission('ads_actions.view');
        $accountId = (int) $request->param('accountId');
        $campaigns = $this->campaignRepo->listByAccount($accountId);
        return $this->json($campaigns);
    }
}
