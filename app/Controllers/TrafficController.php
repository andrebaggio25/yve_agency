<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\AdAccountRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\AdSetRepository;
use App\Repositories\AdRepository;
use App\Repositories\AdMetricsRepository;

class TrafficController extends Controller
{
    public function __construct(
        private readonly AdAccountRepository $accountRepo,
        private readonly CampaignRepository  $campaignRepo,
        private readonly AdSetRepository     $adSetRepo,
        private readonly AdRepository        $adRepo,
        private readonly AdMetricsRepository $metricsRepo,
    ) {}

    // ---------------------------------------------------------------- dashboard

    public function index(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $agencyId = Auth::agencyId();

        $since = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until = $request->query('until', date('Y-m-d'));

        $accounts  = $this->accountRepo->listByAgency($agencyId);
        $campaigns = $this->metricsRepo->metricsPerCampaignForAgency($agencyId, $since, $until);

        $totals = [
            'spend'       => array_sum(array_column($campaigns, 'spend')),
            'impressions' => array_sum(array_column($campaigns, 'impressions')),
            'clicks'      => array_sum(array_column($campaigns, 'clicks')),
            'conversions' => array_sum(array_column($campaigns, 'conversions')),
        ];
        $totals['cpc']  = $totals['clicks']     > 0 ? $totals['spend'] / $totals['clicks']      : 0;
        $totals['cpm']  = $totals['impressions'] > 0 ? ($totals['spend'] / $totals['impressions']) * 1000 : 0;
        $totals['roas'] = !empty($campaigns) && $totals['spend'] > 0
            ? array_sum(array_column($campaigns, 'roas')) / count($campaigns) : 0;

        $dailyChart = [];
        if (!empty($accounts)) {
            $dailyChart = $this->metricsRepo->dailyForAccount($accounts[0]['id'], $since, $until);
        }

        $filterAccount = (int) $request->query('account_id', '0');

        return $this->view('trafego.index', compact('accounts', 'campaigns', 'totals', 'dailyChart', 'since', 'until', 'filterAccount'));
    }

    // ----------------------------------------------------------- campaign drill-down

    public function campaign(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $agencyId = Auth::agencyId();
        $id = (int) $request->param('id');

        $campaign = $this->campaignRepo->findById($id);
        if (!$campaign || (int)$campaign['agency_id'] !== $agencyId) {
            return Response::view('errors.404', [], 404);
        }

        $since = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until = $request->query('until', date('Y-m-d'));

        $adSets    = $this->metricsRepo->metricsPerAdSet($id, $since, $until);
        $dailyChart= $this->metricsRepo->dailyForAccount($campaign['ad_account_id'], $since, $until, 'campaign');

        $totals = [
            'spend'       => array_sum(array_column($adSets, 'spend')),
            'impressions' => array_sum(array_column($adSets, 'impressions')),
            'clicks'      => array_sum(array_column($adSets, 'clicks')),
            'conversions' => array_sum(array_column($adSets, 'conversions')),
        ];
        $totals['cpc'] = $totals['clicks']     > 0 ? $totals['spend'] / $totals['clicks']      : 0;
        $totals['cpm'] = $totals['impressions'] > 0 ? ($totals['spend'] / $totals['impressions']) * 1000 : 0;

        return $this->view('trafego.campaign', compact('campaign', 'adSets', 'totals', 'dailyChart', 'since', 'until'));
    }

    // ----------------------------------------------------------- adset drill-down

    public function adSet(Request $request): Response
    {
        Auth::requirePermission('ads_metrics.view');
        $agencyId = Auth::agencyId();
        $id = (int) $request->param('id');

        $adSet   = $this->adSetRepo->findById($id);
        if (!$adSet) {
            return Response::view('errors.404', [], 404);
        }

        $campaign = $this->campaignRepo->findById($adSet['campaign_id']);
        if (!$campaign || (int)$campaign['agency_id'] !== $agencyId) {
            return Response::view('errors.404', [], 404);
        }

        $since = $request->query('since', date('Y-m-d', strtotime('-30 days')));
        $until = $request->query('until', date('Y-m-d'));

        $ads = $this->metricsRepo->metricsPerAd($id, $since, $until);

        $totals = [
            'spend'       => array_sum(array_column($ads, 'spend')),
            'impressions' => array_sum(array_column($ads, 'impressions')),
            'clicks'      => array_sum(array_column($ads, 'clicks')),
            'conversions' => array_sum(array_column($ads, 'conversions')),
        ];
        $totals['cpc'] = $totals['clicks']     > 0 ? $totals['spend'] / $totals['clicks']      : 0;
        $totals['cpm'] = $totals['impressions'] > 0 ? ($totals['spend'] / $totals['impressions']) * 1000 : 0;

        return $this->view('trafego.adset', compact('adSet', 'campaign', 'ads', 'totals', 'since', 'until'));
    }
}
