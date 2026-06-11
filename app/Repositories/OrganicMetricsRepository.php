<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class OrganicMetricsRepository extends Repository
{
    protected string $table = 'organic_posts';

    // ----------------------------------------------------------------- posts

    public function upsertPost(int $accountId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO organic_posts
                (organic_account_id, platform, platform_post_id,
                 media_type, media_url, thumbnail_url, permalink, caption, posted_at,
                 impressions, reach, likes, comments, shares, saves,
                 video_views, video_plays, engagement_rate,
                 synced_at, created_at, updated_at)
            VALUES
                (:account_id, :platform, :post_id,
                 :media_type, :media_url, :thumb, :permalink, :caption, :posted_at,
                 :impressions, :reach, :likes, :comments, :shares, :saves,
                 :video_views, :video_plays, :er,
                 NOW(), NOW(), NOW())
            ON CONFLICT (organic_account_id, platform_post_id)
            DO UPDATE SET
                media_type     = EXCLUDED.media_type,
                media_url      = EXCLUDED.media_url,
                thumbnail_url  = EXCLUDED.thumbnail_url,
                permalink      = EXCLUDED.permalink,
                caption        = EXCLUDED.caption,
                impressions    = EXCLUDED.impressions,
                reach          = EXCLUDED.reach,
                likes          = EXCLUDED.likes,
                comments       = EXCLUDED.comments,
                shares         = EXCLUDED.shares,
                saves          = EXCLUDED.saves,
                video_views    = EXCLUDED.video_views,
                video_plays    = EXCLUDED.video_plays,
                engagement_rate= EXCLUDED.engagement_rate,
                synced_at      = NOW(),
                updated_at     = NOW()
            RETURNING id
        ");
        $stmt->execute([
            ':account_id'  => $accountId,
            ':platform'    => $data['platform'],
            ':post_id'     => $data['platform_post_id'],
            ':media_type'  => $data['media_type']   ?? null,
            ':media_url'   => $data['media_url']    ?? null,
            ':thumb'       => $data['thumbnail_url'] ?? null,
            ':permalink'   => $data['permalink']    ?? null,
            ':caption'     => $data['caption']      ?? null,
            ':posted_at'   => $data['posted_at']    ?? null,
            ':impressions' => (int)   ($data['impressions']   ?? 0),
            ':reach'       => (int)   ($data['reach']         ?? 0),
            ':likes'       => (int)   ($data['likes']         ?? 0),
            ':comments'    => (int)   ($data['comments']      ?? 0),
            ':shares'      => (int)   ($data['shares']        ?? 0),
            ':saves'       => (int)   ($data['saves']         ?? 0),
            ':video_views' => (int)   ($data['video_views']   ?? 0),
            ':video_plays' => (int)   ($data['video_plays']   ?? 0),
            ':er'          => (float) ($data['engagement_rate'] ?? 0),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function listPosts(int $accountId, array $filters = []): array
    {
        $where  = ['p.organic_account_id = :account_id'];
        $params = [':account_id' => $accountId];

        if (!empty($filters['media_type'])) {
            $where[] = 'p.media_type = :media_type';
            $params[':media_type'] = $filters['media_type'];
        }
        if (!empty($filters['since'])) {
            $where[] = 'p.posted_at >= :since';
            $params[':since'] = $filters['since'];
        }
        if (!empty($filters['until'])) {
            $where[] = 'p.posted_at <= :until::date + interval \'1 day\'';
            $params[':until'] = $filters['until'];
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = match ($filters['sort'] ?? 'date') {
            'reach'       => 'p.reach DESC',
            'likes'       => 'p.likes DESC',
            'impressions' => 'p.impressions DESC',
            'engagement'  => 'p.engagement_rate DESC',
            default       => 'p.posted_at DESC',
        };

        return $this->all("
            SELECT p.*
            FROM organic_posts p
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT 50
        ", $params);
    }

    /** KPIs agregados de posts de uma conta no período */
    public function summaryForAccount(int $accountId, string $since, string $until): array
    {
        return $this->first("
            SELECT
                COUNT(*)                                AS total_posts,
                COALESCE(SUM(impressions), 0)           AS total_impressions,
                COALESCE(SUM(reach), 0)                 AS total_reach,
                COALESCE(SUM(likes), 0)                 AS total_likes,
                COALESCE(SUM(comments), 0)              AS total_comments,
                COALESCE(SUM(shares), 0)                AS total_shares,
                COALESCE(SUM(saves), 0)                 AS total_saves,
                COALESCE(SUM(video_views), 0)           AS total_video_views,
                COALESCE(AVG(engagement_rate), 0)       AS avg_engagement_rate,
                COALESCE(AVG(reach), 0)                 AS avg_reach,
                COUNT(*) FILTER (WHERE media_type = 'IMAGE')           AS image_count,
                COUNT(*) FILTER (WHERE media_type = 'VIDEO')           AS video_count,
                COUNT(*) FILTER (WHERE media_type = 'REEL')            AS reel_count,
                COUNT(*) FILTER (WHERE media_type = 'CAROUSEL_ALBUM')  AS carousel_count,
                COUNT(*) FILTER (WHERE media_type = 'STORY')           AS story_count
            FROM organic_posts
            WHERE organic_account_id = :account_id
              AND posted_at >= :since
              AND posted_at <= :until::date + interval '1 day'
        ", [':account_id' => $accountId, ':since' => $since, ':until' => $until]) ?? [];
    }

    // --------------------------------------------------------------- daily

    public function upsertDaily(int $accountId, array $data): void
    {
        $this->pdo->prepare("
            INSERT INTO organic_daily
                (organic_account_id, date,
                 followers_count, followers_gained, followers_lost,
                 impressions, reach, profile_views, website_clicks, posts_count,
                 created_at)
            VALUES
                (:account_id, :date,
                 :followers_count, :followers_gained, :followers_lost,
                 :impressions, :reach, :profile_views, :website_clicks, :posts_count,
                 NOW())
            ON CONFLICT (organic_account_id, date)
            DO UPDATE SET
                followers_count  = EXCLUDED.followers_count,
                followers_gained = EXCLUDED.followers_gained,
                followers_lost   = EXCLUDED.followers_lost,
                impressions      = EXCLUDED.impressions,
                reach            = EXCLUDED.reach,
                profile_views    = EXCLUDED.profile_views,
                website_clicks   = EXCLUDED.website_clicks,
                posts_count      = EXCLUDED.posts_count
        ")->execute([
            ':account_id'       => $accountId,
            ':date'             => $data['date'],
            ':followers_count'  => (int) ($data['followers_count']  ?? 0),
            ':followers_gained' => (int) ($data['followers_gained'] ?? 0),
            ':followers_lost'   => (int) ($data['followers_lost']   ?? 0),
            ':impressions'      => (int) ($data['impressions']      ?? 0),
            ':reach'            => (int) ($data['reach']            ?? 0),
            ':profile_views'    => (int) ($data['profile_views']    ?? 0),
            ':website_clicks'   => (int) ($data['website_clicks']   ?? 0),
            ':posts_count'      => (int) ($data['posts_count']      ?? 0),
        ]);
    }

    public function dailyForChart(int $accountId, string $since, string $until): array
    {
        return $this->all("
            SELECT date, followers_count, followers_gained, impressions, reach, profile_views
            FROM organic_daily
            WHERE organic_account_id = :account_id
              AND date BETWEEN :since AND :until
            ORDER BY date
        ", [':account_id' => $accountId, ':since' => $since, ':until' => $until]);
    }

    public function topPosts(int $accountId, string $since, string $until, string $by = 'reach', int $limit = 9): array
    {
        $orderCol = match ($by) {
            'likes'       => 'likes',
            'impressions' => 'impressions',
            'engagement'  => 'engagement_rate',
            default       => 'reach',
        };
        return $this->all("
            SELECT *
            FROM organic_posts
            WHERE organic_account_id = :account_id
              AND posted_at >= :since
              AND posted_at <= :until::date + interval '1 day'
            ORDER BY {$orderCol} DESC
            LIMIT {$limit}
        ", [':account_id' => $accountId, ':since' => $since, ':until' => $until]);
    }

    /** Resumo por agência (multi-conta) */
    public function summaryForAgency(int $agencyId, string $since, string $until): array
    {
        return $this->all("
            SELECT
                oa.id, oa.name, oa.username, oa.platform,
                oa.followers_count, oa.profile_picture_url,
                cl.name AS client_name,
                COUNT(p.id)                              AS total_posts,
                COALESCE(SUM(p.reach), 0)               AS total_reach,
                COALESCE(SUM(p.likes), 0)               AS total_likes,
                COALESCE(SUM(p.impressions), 0)         AS total_impressions,
                COALESCE(AVG(p.engagement_rate), 0)     AS avg_engagement_rate
            FROM organic_accounts oa
            LEFT JOIN clients cl ON cl.id = oa.client_id
            LEFT JOIN organic_posts p ON p.organic_account_id = oa.id
                AND p.posted_at >= :since
                AND p.posted_at <= :until::date + interval '1 day'
            WHERE oa.agency_id = :agency_id
            GROUP BY oa.id, oa.name, oa.username, oa.platform,
                     oa.followers_count, oa.profile_picture_url, cl.name
            ORDER BY total_reach DESC
        ", [':agency_id' => $agencyId, ':since' => $since, ':until' => $until]);
    }
}
