<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Integração com Meta Graph API para métricas orgânicas.
 * Suporta Facebook Pages e Instagram Business (via Business Discovery API).
 */
class MetaOrganicService
{
    private Client $http;
    private string $base = 'https://graph.facebook.com/v21.0/';

    public function __construct()
    {
        $this->http = new Client(['base_uri' => $this->base, 'timeout' => 30]);
    }

    // -------------------------------------------------------- account info

    /** Busca info de uma Facebook Page + Instagram Business Account vinculada */
    public function fetchPageInfo(string $token, string $pageId): array
    {
        return $this->get($pageId, [
            'fields'       => 'id,name,username,picture{url},about,website,fan_count,instagram_business_account',
            'access_token' => $token,
        ]);
    }

    /** Busca info do Instagram Business Account */
    public function fetchInstagramInfo(string $token, string $igUserId): array
    {
        return $this->get($igUserId, [
            'fields'       => 'id,name,username,biography,profile_picture_url,website,followers_count,follows_count,media_count',
            'access_token' => $token,
        ]);
    }

    /** Troca user token por page token de longa duração */
    public function fetchPageToken(string $userToken, string $pageId): array
    {
        $pages = $this->get('me/accounts', [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token',
        ]);
        foreach ($pages['data'] ?? [] as $p) {
            if ($p['id'] === $pageId) {
                return ['access_token' => $p['access_token']];
            }
        }
        throw new \RuntimeException("Página {$pageId} não encontrada nas contas do usuário.");
    }

    // --------------------------------------------------------- posts (IG)

    public function fetchInstagramMedia(string $token, string $igUserId, ?string $after = null): array
    {
        $params = [
            'fields'       => 'id,media_type,media_url,thumbnail_url,permalink,caption,timestamp,'
                . 'like_count,comments_count',
            'limit'        => 50,
            'access_token' => $token,
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $raw  = $this->get("{$igUserId}/media", $params);
        $next = ($raw['paging']['next'] ?? false) ? ($raw['paging']['cursors']['after'] ?? null) : null;

        return ['data' => $raw['data'] ?? [], 'next' => $next];
    }

    /** Métricas detalhadas de um post do Instagram */
    public function fetchInstagramPostInsights(string $token, string $mediaId, string $mediaType): array
    {
        $metrics = 'impressions,reach,saved';
        if (in_array($mediaType, ['VIDEO', 'REEL'], true)) {
            $metrics .= ',video_views,plays';
        }
        if ($mediaType === 'STORY') {
            $metrics = 'impressions,reach,exits,replies,taps_forward,taps_back';
        }

        try {
            $raw = $this->get("{$mediaId}/insights", [
                'metric'       => $metrics,
                'access_token' => $token,
            ]);
            return $this->indexInsightsByName($raw['data'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Insights diários da conta Instagram (impressões, alcance, seguidores) */
    public function fetchInstagramDailyInsights(string $token, string $igUserId, string $since, string $until): array
    {
        try {
            $raw = $this->get("{$igUserId}/insights", [
                'metric'       => 'impressions,reach,profile_views,follower_count,website_clicks',
                'period'       => 'day',
                'since'        => strtotime($since),
                'until'        => strtotime($until . ' +1 day'),
                'access_token' => $token,
            ]);
            return $this->pivotDailyInsights($raw['data'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    // -------------------------------------------------------- posts (FB Page)

    public function fetchPagePosts(string $token, string $pageId, ?string $after = null): array
    {
        $params = [
            'fields'       => 'id,message,story,full_picture,permalink_url,created_time,type,'
                . 'shares,reactions.summary(total_count),comments.summary(total_count)',
            'limit'        => 50,
            'access_token' => $token,
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $raw  = $this->get("{$pageId}/posts", $params);
        $next = ($raw['paging']['next'] ?? false) ? ($raw['paging']['cursors']['after'] ?? null) : null;

        return ['data' => $raw['data'] ?? [], 'next' => $next];
    }

    public function fetchPagePostInsights(string $token, string $postId): array
    {
        try {
            $raw = $this->get("{$postId}/insights", [
                'metric'       => 'post_impressions,post_impressions_unique,post_engaged_users,post_video_views',
                'access_token' => $token,
            ]);
            return $this->indexInsightsByName($raw['data'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    // --------------------------------------------------------------- helpers

    private function indexInsightsByName(array $data): array
    {
        $result = [];
        foreach ($data as $metric) {
            $result[$metric['name']] = $metric['values'][0]['value'] ?? $metric['value'] ?? 0;
        }
        return $result;
    }

    /**
     * Pivota insights diários da API (array por métrica com array de valores por dia)
     * → array por data com todas as métricas
     */
    private function pivotDailyInsights(array $data): array
    {
        $byDate = [];
        foreach ($data as $metric) {
            $name = $metric['name'];
            foreach ($metric['values'] ?? [] as $point) {
                $date = date('Y-m-d', $point['end_time'] ? strtotime($point['end_time']) - 1 : 0);
                $byDate[$date][$name] = (int) ($point['value'] ?? 0);
            }
        }
        return $byDate;
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
            throw new \RuntimeException('Meta Organic API: ' . $body, (int) $e->getCode(), $e);
        }
    }
}
