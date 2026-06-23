<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformSettingsRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MetaAdsService
{
    private Client $http;
    private string $apiVersion;
    private string $baseUri;

    public function __construct(
        private readonly PlatformSettingsRepository $platformSettings,
    ) {
        $this->apiVersion = env('META_API_VERSION', 'v21.0');
        $this->baseUri    = "https://graph.facebook.com/{$this->apiVersion}/";
        $this->http       = new Client(['base_uri' => $this->baseUri, 'timeout' => 30]);
    }

    private function appCredentials(): array
    {
        $s = $this->platformSettings->getMultiple(['meta_app_id', 'meta_app_secret']);
        return [$s['meta_app_id'] ?? '', $s['meta_app_secret'] ?? ''];
    }

    // ------------------------------------------------------------- OAuth (code → token, contas)

    /**
     * Troca o `code` do callback OAuth por um token de acesso (short-lived).
     * @return array{access_token?: string, token_type?: string, expires_in?: int}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        [$appId, $appSecret] = $this->appCredentials();

        return $this->get('oauth/access_token', [
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);
    }

    /**
     * Lista as contas de anúncios vinculadas ao usuário do token.
     * @return array<int,array{id:string,name?:string,currency?:string,account_status?:int}>
     */
    public function fetchUserAdAccounts(string $token): array
    {
        $data = $this->get('me/adaccounts', [
            'fields'       => 'id,name,currency,account_status',
            'limit'        => 200,
            'access_token' => $token,
        ]);

        return $data['data'] ?? [];
    }

    /** Indica se um token ainda é válido (usado para detectar expiração). */
    public function isTokenValid(string $token): bool
    {
        try {
            $data = $this->validateToken($token);
            return (bool) ($data['is_valid'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ token

    public function validateToken(string $token): array
    {
        [$appId, $appSecret] = $this->appCredentials();

        $data = $this->get('debug_token', [
            'input_token'  => $token,
            'access_token' => "{$appId}|{$appSecret}",
        ]);

        return $data['data'] ?? [];
    }

    public function exchangeForLongLivedToken(string $shortToken): array
    {
        [$appId, $appSecret] = $this->appCredentials();

        return $this->get('oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $appId,
            'client_secret'     => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);
    }

    public function fetchAccountInfo(string $token, string $accountId): array
    {
        return $this->get("act_{$accountId}", [
            'fields'       => 'id,name,currency,timezone_name,account_status',
            'access_token' => $token,
        ]);
    }

    // --------------------------------------------------------------- campaigns

    /**
     * @return array{data: array, next: ?string}
     */
    public function fetchCampaigns(string $token, string $accountId, ?string $after = null): array
    {
        $params = [
            'fields'       => 'id,name,status,objective,buying_type,daily_budget,lifetime_budget,start_time,stop_time',
            'limit'        => 200,
            'access_token' => $token,
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $raw  = $this->get("act_{$accountId}/campaigns", $params);
        $next = $raw['paging']['cursors']['after'] ?? null;
        if (!($raw['paging']['next'] ?? false)) {
            $next = null;
        }

        return ['data' => $raw['data'] ?? [], 'next' => $next];
    }

    // --------------------------------------------------------------- ad sets

    public function fetchAdSets(string $token, string $campaignId, ?string $after = null): array
    {
        $params = [
            'fields'       => 'id,name,status,daily_budget,lifetime_budget,optimization_goal,billing_event,bid_strategy,bid_amount,targeting,start_time,stop_time',
            'limit'        => 200,
            'access_token' => $token,
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $raw  = $this->get("{$campaignId}/adsets", $params);
        $next = $raw['paging']['cursors']['after'] ?? null;
        if (!($raw['paging']['next'] ?? false)) {
            $next = null;
        }

        return ['data' => $raw['data'] ?? [], 'next' => $next];
    }

    // -------------------------------------------------------------------- ads

    public function fetchAds(string $token, string $adSetId, ?string $after = null): array
    {
        $params = [
            'fields'       => 'id,name,status,creative{id,name,title,body,image_url,thumbnail_url,call_to_action_type,link_url,object_url,effective_object_story_id,asset_feed_spec}',
            'limit'        => 200,
            'access_token' => $token,
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $raw  = $this->get("{$adSetId}/ads", $params);
        $next = $raw['paging']['cursors']['after'] ?? null;
        if (!($raw['paging']['next'] ?? false)) {
            $next = null;
        }

        $ads = array_map([$this, 'normalizeAd'], $raw['data'] ?? []);
        return ['data' => $ads, 'next' => $next];
    }

    private function normalizeAd(array $raw): array
    {
        $cr = $raw['creative'] ?? [];
        return [
            'platform_id'     => $raw['id'],
            'name'            => $raw['name'],
            'status'          => strtolower($raw['status'] ?? 'unknown'),
            'creative_type'   => $this->detectCreativeType($cr),
            'headline'        => $cr['title'] ?? null,
            'body'            => $cr['body'] ?? null,
            'image_url'       => $cr['image_url'] ?? null,
            'thumbnail_url'   => $cr['thumbnail_url'] ?? null,
            'call_to_action'  => $cr['call_to_action_type'] ?? null,
            'destination_url' => $cr['link_url'] ?? $cr['object_url'] ?? null,
        ];
    }

    private function detectCreativeType(array $cr): string
    {
        if (!empty($cr['asset_feed_spec'])) {
            return 'dynamic';
        }
        if (!empty($cr['thumbnail_url']) && empty($cr['image_url'])) {
            return 'video';
        }
        if (!empty($cr['image_url'])) {
            return 'image';
        }
        return 'unknown';
    }

    // ------------------------------------------------------------- insights

    /**
     * Busca insights de um nível: account, campaign, adset, ad
     * Retorna array de linhas por dia
     */
    public function fetchInsights(string $token, string $objectId, string $level, string $since, string $until): array
    {
        $rows   = [];
        $after  = null;

        do {
            $params = [
                'level'        => $level,
                'fields'       => implode(',', [
                    'account_id', 'campaign_id', 'adset_id', 'ad_id',
                    'impressions', 'reach', 'frequency',
                    'clicks', 'inline_link_clicks',
                    'spend', 'cpc', 'cpm', 'ctr', 'cpp',
                    'actions', 'action_values',
                    'video_avg_time_watched_actions',
                    'video_p25_watched_actions',
                    'video_p50_watched_actions',
                    'video_p75_watched_actions',
                    'video_p100_watched_actions',
                    'video_play_actions',
                ]),
                'time_increment' => 1,
                'time_range'   => json_encode(['since' => $since, 'until' => $until]),
                'limit'        => 500,
                'access_token' => $token,
            ];
            if ($after) {
                $params['after'] = $after;
            }

            $raw  = $this->get("{$objectId}/insights", $params);
            $rows = array_merge($rows, $raw['data'] ?? []);
            $after = $raw['paging']['cursors']['after'] ?? null;
            if (!($raw['paging']['next'] ?? false)) {
                $after = null;
            }
        } while ($after);

        return $rows;
    }

    // -------------------------------------------------------- normalize insight

    public function normalizeInsight(array $row, string $entityType, int $entityId, int $adAccountId): array
    {
        $conversions     = $this->extractAction($row['actions'] ?? [], 'offsite_conversion.fb_pixel_purchase', 'purchase');
        $conversionValue = $this->extractActionValue($row['action_values'] ?? [], 'offsite_conversion.fb_pixel_purchase', 'purchase');
        $spend           = (float) ($row['spend'] ?? 0);

        return [
            'ad_account_id'    => $adAccountId,
            'entity_type'      => $entityType,
            'entity_id'        => $entityId,
            'date'             => $row['date_start'] ?? $row['date_stop'],
            'platform'         => 'meta',
            'impressions'      => (int)   ($row['impressions'] ?? 0),
            'reach'            => (int)   ($row['reach'] ?? 0),
            'frequency'        => (float) ($row['frequency'] ?? 0),
            'clicks'           => (int)   ($row['clicks'] ?? 0),
            'link_clicks'      => (int)   ($row['inline_link_clicks'] ?? 0),
            'spend'            => $spend,
            'cpc'              => (float) ($row['cpc'] ?? 0),
            'cpm'              => (float) ($row['cpm'] ?? 0),
            'ctr'              => (float) ($row['ctr'] ?? 0),
            'cpp'              => (float) ($row['cpp'] ?? 0),
            'conversions'      => $conversions,
            'conversion_value' => $conversionValue,
            'roas'             => $spend > 0 ? round($conversionValue / $spend, 4) : 0,
            'video_views'      => $this->extractAction($row['video_play_actions'] ?? [], 'video_view'),
            'video_p25'        => $this->extractAction($row['video_p25_watched_actions'] ?? [], 'video_view'),
            'video_p50'        => $this->extractAction($row['video_p50_watched_actions'] ?? [], 'video_view'),
            'video_p75'        => $this->extractAction($row['video_p75_watched_actions'] ?? [], 'video_view'),
            'video_p100'       => $this->extractAction($row['video_p100_watched_actions'] ?? [], 'video_view'),
        ];
    }

    private function extractAction(array $actions, string ...$types): int
    {
        foreach ($actions as $a) {
            if (in_array($a['action_type'] ?? '', $types, true)) {
                return (int) ($a['value'] ?? 0);
            }
        }
        return 0;
    }

    private function extractActionValue(array $actions, string ...$types): float
    {
        foreach ($actions as $a) {
            if (in_array($a['action_type'] ?? '', $types, true)) {
                return (float) ($a['value'] ?? 0);
            }
        }
        return 0.0;
    }

    // ---------------------------------------------------------- write operations

    /** Atualiza o status de uma campanha, conjunto ou anúncio */
    public function updateStatus(string $objectId, string $token, string $status): void
    {
        $this->post($objectId, ['status' => $status, 'access_token' => $token]);
    }

    /**
     * Atualiza orçamento diário de uma campanha ou conjunto.
     * $value pode ser "R$ 50/dia" ou "50" — extrai o número.
     */
    public function updateBudget(string $objectId, string $token, ?string $value): void
    {
        $amount = $this->parseBudgetToCents($value);
        if ($amount <= 0) {
            return;
        }
        $this->post($objectId, ['daily_budget' => $amount, 'access_token' => $token]);
    }

    /**
     * Converte um valor textual de orçamento em centavos.
     * Ex.: "R$ 50/dia" → 5000 · "R$ 1.250,50" → 125050 · null/"" → 0
     */
    public function parseBudgetToCents(?string $value): int
    {
        if ($value === null) {
            return 0;
        }
        // Remove separador de milhar (.) e extrai o número; vírgula vira decimal.
        preg_match('/[\d.,]+/', str_replace('.', '', $value), $m);
        $amount = (int) round(((float) str_replace(',', '.', $m[0] ?? '0')) * 100);
        return $amount > 0 ? $amount : 0;
    }

    // ---------------------------------------------------------------- helpers

    private function post(string $endpoint, array $params = []): array
    {
        try {
            $response = $this->http->post($endpoint, ['form_params' => $params]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage();
            throw new \RuntimeException('Meta API error: ' . $body, (int) $e->getCode(), $e);
        }
    }

    private function get(string $endpoint, array $params = []): array
    {
        try {
            $response = $this->http->get($endpoint, ['query' => $params]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage();
            throw new \RuntimeException('Meta API error: ' . $body, (int) $e->getCode(), $e);
        }
    }
}
