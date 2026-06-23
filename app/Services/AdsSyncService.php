<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdAccountRepository;
use App\Repositories\CampaignRepository;
use App\Repositories\AdSetRepository;
use App\Repositories\AdRepository;
use App\Repositories\AdMetricsRepository;

class AdsSyncService
{
    public function __construct(
        private readonly AdAccountRepository $accountRepo,
        private readonly CampaignRepository  $campaignRepo,
        private readonly AdSetRepository     $adSetRepo,
        private readonly AdRepository        $adRepo,
        private readonly AdMetricsRepository $metricsRepo,
        private readonly MetaAdsService      $meta,
    ) {}

    /** Sincroniza todos os accounts ativos — usado pelo cron */
    public function syncAll(): array
    {
        $accounts = $this->accountRepo->findAllActive();
        $results  = [];
        foreach ($accounts as $account) {
            try {
                $results[$account['id']] = $this->syncAccount($account);
            } catch (\Throwable $e) {
                $results[$account['id']] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /** Sincroniza um único ad account (campanhas, conjuntos, anúncios e métricas) */
    public function syncAccount(array $account): array
    {
        $token     = $account['access_token'];
        $accountId = $account['id'];
        $platformId= $account['platform_account_id'];
        $platform  = $account['platform'];
        $since     = date('Y-m-d', strtotime("-{$account['sync_days_back']} days"));
        $until     = date('Y-m-d');

        $stats = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0, 'metric_rows' => 0];

        // ---- 0. Valida o token antes de sincronizar; se expirou, sinaliza e interrompe
        if (!$this->meta->isTokenValid($token)) {
            $this->accountRepo->setStatus($accountId, 'token_expired');
            throw new \RuntimeException('Token do Facebook expirado ou inválido. Reconecte a conta para retomar a sincronização.');
        }

        // ---- 1. Campanhas
        $after = null;
        do {
            ['data' => $campaigns, 'next' => $after] = $this->meta->fetchCampaigns($token, $platformId, $after);
            foreach ($campaigns as $c) {
                $campaignDbId = $this->campaignRepo->upsert($accountId, $platform, [
                    'platform_id'     => $c['id'],
                    'name'            => $c['name'],
                    'status'          => strtolower($c['status']),
                    'objective'       => $c['objective'] ?? null,
                    'buying_type'     => $c['buying_type'] ?? null,
                    'daily_budget'    => isset($c['daily_budget'])    ? (int) $c['daily_budget'] / 100    : null,
                    'lifetime_budget' => isset($c['lifetime_budget']) ? (int) $c['lifetime_budget'] / 100 : null,
                    'start_time'      => $c['start_time'] ?? null,
                    'stop_time'       => $c['stop_time']  ?? null,
                ]);
                $stats['campaigns']++;

                // ---- 2. Conjuntos de anúncio
                $adsetAfter = null;
                do {
                    ['data' => $adsets, 'next' => $adsetAfter] = $this->meta->fetchAdSets($token, $c['id'], $adsetAfter);
                    foreach ($adsets as $s) {
                        $adSetDbId = $this->adSetRepo->upsert($campaignDbId, [
                            'platform_id'       => $s['id'],
                            'name'              => $s['name'],
                            'status'            => strtolower($s['status']),
                            'daily_budget'      => isset($s['daily_budget'])    ? (int) $s['daily_budget'] / 100    : null,
                            'lifetime_budget'   => isset($s['lifetime_budget']) ? (int) $s['lifetime_budget'] / 100 : null,
                            'optimization_goal' => $s['optimization_goal'] ?? null,
                            'billing_event'     => $s['billing_event'] ?? null,
                            'bid_strategy'      => $s['bid_strategy'] ?? null,
                            'bid_amount'        => isset($s['bid_amount']) ? (int) $s['bid_amount'] / 100 : null,
                            'targeting_summary' => isset($s['targeting']) ? json_encode($s['targeting']) : null,
                            'start_time'        => $s['start_time'] ?? null,
                            'stop_time'         => $s['stop_time']  ?? null,
                        ]);
                        $stats['adsets']++;

                        // ---- 3. Anúncios com criativo completo
                        $adAfter = null;
                        do {
                            ['data' => $ads, 'next' => $adAfter] = $this->meta->fetchAds($token, $s['id'], $adAfter);
                            foreach ($ads as $ad) {
                                $adDbId = $this->adRepo->upsert($adSetDbId, $ad);
                                $stats['ads']++;

                                // Métricas de anúncio
                                $adInsights = $this->meta->fetchInsights($token, $ad['platform_id'], 'ad', $since, $until);
                                foreach ($adInsights as $row) {
                                    $this->metricsRepo->upsert(
                                        $this->meta->normalizeInsight($row, 'ad', $adDbId, $accountId)
                                    );
                                    $stats['metric_rows']++;
                                }
                            }
                        } while ($adAfter);

                        // Métricas de conjunto de anúncio
                        $adsetInsights = $this->meta->fetchInsights($token, $s['id'], 'adset', $since, $until);
                        foreach ($adsetInsights as $row) {
                            $this->metricsRepo->upsert(
                                $this->meta->normalizeInsight($row, 'adset', $adSetDbId, $accountId)
                            );
                            $stats['metric_rows']++;
                        }
                    }
                } while ($adsetAfter);

                // Métricas de campanha
                $campaignInsights = $this->meta->fetchInsights($token, $c['id'], 'campaign', $since, $until);
                foreach ($campaignInsights as $row) {
                    $this->metricsRepo->upsert(
                        $this->meta->normalizeInsight($row, 'campaign', $campaignDbId, $accountId)
                    );
                    $stats['metric_rows']++;
                }
            }
        } while ($after);

        $this->accountRepo->updateSyncedAt($accountId);
        return $stats;
    }
}
