<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrganicAccountRepository;
use App\Repositories\OrganicMetricsRepository;

class OrganicSyncService
{
    public function __construct(
        private readonly OrganicAccountRepository $accountRepo,
        private readonly OrganicMetricsRepository $metricsRepo,
        private readonly MetaOrganicService       $meta,
    ) {}

    /** Sincroniza todas as contas orgânicas ativas — usado pelo cron */
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

    /** Sincroniza uma conta (posts + métricas diárias) */
    public function syncAccount(array $account): array
    {
        return match ($account['platform']) {
            'instagram' => $this->syncInstagram($account),
            'facebook'  => $this->syncFacebook($account),
            default     => throw new \RuntimeException("Plataforma '{$account['platform']}' não suportada."),
        };
    }

    // --------------------------------------------------------------- instagram

    private function syncInstagram(array $account): array
    {
        $token    = $account['access_token'];
        $igId     = $account['instagram_user_id'] ?? $account['platform_page_id'];
        $accountId= $account['id'];
        $since    = date('Y-m-d', strtotime("-{$account['sync_days_back']} days"));
        $until    = date('Y-m-d');

        $stats = ['posts' => 0, 'daily_rows' => 0];

        // 1. Info atualizada da conta
        try {
            $info = $this->meta->fetchInstagramInfo($token, $igId);
            $this->accountRepo->updateSyncedAt($accountId, [
                'followers_count' => $info['followers_count'] ?? null,
                'following_count' => $info['follows_count']   ?? null,
                'media_count'     => $info['media_count']     ?? null,
            ]);
        } catch (\Throwable) {}

        // 2. Posts com métricas
        $after = null;
        do {
            ['data' => $media, 'next' => $after] = $this->meta->fetchInstagramMedia($token, $igId, $after);

            foreach ($media as $m) {
                $postedAt = $m['timestamp'] ?? null;
                if ($postedAt && $postedAt < $since) {
                    $after = null; // para de paginar — posts ordenados por data desc
                    break;
                }

                $insights = $this->meta->fetchInstagramPostInsights($token, $m['id'], $m['media_type'] ?? 'IMAGE');
                $reach      = (int) ($insights['reach']  ?? 0);
                $followers  = max($account['followers_count'], 1);
                $engagement = $reach > 0
                    ? (((int)($m['like_count'] ?? 0) + (int)($m['comments_count'] ?? 0)) / $followers) * 100
                    : 0.0;

                $this->metricsRepo->upsertPost($accountId, [
                    'platform'         => 'instagram',
                    'platform_post_id' => $m['id'],
                    'media_type'       => $m['media_type'] ?? 'IMAGE',
                    'media_url'        => $m['media_url']    ?? null,
                    'thumbnail_url'    => $m['thumbnail_url'] ?? null,
                    'permalink'        => $m['permalink']    ?? null,
                    'caption'          => mb_substr($m['caption'] ?? '', 0, 2200),
                    'posted_at'        => $postedAt,
                    'impressions'      => (int) ($insights['impressions'] ?? 0),
                    'reach'            => $reach,
                    'likes'            => (int) ($m['like_count']     ?? 0),
                    'comments'         => (int) ($m['comments_count'] ?? 0),
                    'shares'           => 0,
                    'saves'            => (int) ($insights['saved']       ?? 0),
                    'video_views'      => (int) ($insights['video_views'] ?? 0),
                    'video_plays'      => (int) ($insights['plays']       ?? 0),
                    'engagement_rate'  => round($engagement, 4),
                ]);
                $stats['posts']++;
            }
        } while ($after);

        // 3. Métricas diárias da conta
        $dailyMap = $this->meta->fetchInstagramDailyInsights($token, $igId, $since, $until);
        foreach ($dailyMap as $date => $row) {
            $this->metricsRepo->upsertDaily($accountId, [
                'date'            => $date,
                'followers_count' => $account['followers_count'],
                'followers_gained'=> (int) ($row['follower_count']  ?? 0),
                'followers_lost'  => 0,
                'impressions'     => (int) ($row['impressions']     ?? 0),
                'reach'           => (int) ($row['reach']           ?? 0),
                'profile_views'   => (int) ($row['profile_views']   ?? 0),
                'website_clicks'  => (int) ($row['website_clicks']  ?? 0),
                'posts_count'     => 0,
            ]);
            $stats['daily_rows']++;
        }

        return $stats;
    }

    // ---------------------------------------------------------------- facebook

    private function syncFacebook(array $account): array
    {
        $token    = $account['access_token'];
        $pageId   = $account['platform_page_id'];
        $accountId= $account['id'];
        $since    = date('Y-m-d', strtotime("-{$account['sync_days_back']} days"));

        $stats = ['posts' => 0];

        $after = null;
        do {
            ['data' => $posts, 'next' => $after] = $this->meta->fetchPagePosts($token, $pageId, $after);

            foreach ($posts as $p) {
                $postedAt = $p['created_time'] ?? null;
                if ($postedAt && date('Y-m-d', strtotime($postedAt)) < $since) {
                    $after = null;
                    break;
                }

                $insights = $this->meta->fetchPagePostInsights($token, $p['id']);
                $this->metricsRepo->upsertPost($accountId, [
                    'platform'         => 'facebook',
                    'platform_post_id' => $p['id'],
                    'media_type'       => strtoupper($p['type'] ?? 'IMAGE'),
                    'media_url'        => $p['full_picture'] ?? null,
                    'permalink'        => $p['permalink_url'] ?? null,
                    'caption'          => mb_substr($p['message'] ?? $p['story'] ?? '', 0, 2200),
                    'posted_at'        => $postedAt,
                    'impressions'      => (int) ($insights['post_impressions']        ?? 0),
                    'reach'            => (int) ($insights['post_impressions_unique'] ?? 0),
                    'likes'            => (int) ($p['reactions']['summary']['total_count'] ?? 0),
                    'comments'         => (int) ($p['comments']['summary']['total_count']  ?? 0),
                    'shares'           => (int) ($p['shares']['count'] ?? 0),
                    'saves'            => 0,
                    'video_views'      => (int) ($insights['post_video_views'] ?? 0),
                    'video_plays'      => 0,
                    'engagement_rate'  => 0,
                ]);
                $stats['posts']++;
            }
        } while ($after);

        $this->accountRepo->updateSyncedAt($accountId);
        return $stats;
    }
}
