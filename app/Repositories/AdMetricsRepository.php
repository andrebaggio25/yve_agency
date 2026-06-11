<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class AdMetricsRepository extends Repository
{
    protected string $table = 'ad_daily_metrics';

    public function upsert(array $data): void
    {
        $this->pdo->prepare("
            INSERT INTO ad_daily_metrics
                (ad_account_id, entity_type, entity_id, date, platform,
                 impressions, reach, frequency, clicks, link_clicks,
                 spend, cpc, cpm, ctr, cpp,
                 conversions, conversion_value, roas,
                 video_views, video_p25, video_p50, video_p75, video_p100,
                 created_at, updated_at)
            VALUES
                (:account_id, :entity_type, :entity_id, :date, :platform,
                 :impressions, :reach, :frequency, :clicks, :link_clicks,
                 :spend, :cpc, :cpm, :ctr, :cpp,
                 :conversions, :conversion_value, :roas,
                 :video_views, :video_p25, :video_p50, :video_p75, :video_p100,
                 NOW(), NOW())
            ON CONFLICT (entity_type, entity_id, date)
            DO UPDATE SET
                impressions      = EXCLUDED.impressions,
                reach            = EXCLUDED.reach,
                frequency        = EXCLUDED.frequency,
                clicks           = EXCLUDED.clicks,
                link_clicks      = EXCLUDED.link_clicks,
                spend            = EXCLUDED.spend,
                cpc              = EXCLUDED.cpc,
                cpm              = EXCLUDED.cpm,
                ctr              = EXCLUDED.ctr,
                cpp              = EXCLUDED.cpp,
                conversions      = EXCLUDED.conversions,
                conversion_value = EXCLUDED.conversion_value,
                roas             = EXCLUDED.roas,
                video_views      = EXCLUDED.video_views,
                video_p25        = EXCLUDED.video_p25,
                video_p50        = EXCLUDED.video_p50,
                video_p75        = EXCLUDED.video_p75,
                video_p100       = EXCLUDED.video_p100,
                updated_at       = NOW()
        ")->execute([
            ':account_id'       => $data['ad_account_id'],
            ':entity_type'      => $data['entity_type'],
            ':entity_id'        => $data['entity_id'],
            ':date'             => $data['date'],
            ':platform'         => $data['platform'] ?? 'meta',
            ':impressions'      => (int)   ($data['impressions']      ?? 0),
            ':reach'            => (int)   ($data['reach']            ?? 0),
            ':frequency'        => (float) ($data['frequency']        ?? 0),
            ':clicks'           => (int)   ($data['clicks']           ?? 0),
            ':link_clicks'      => (int)   ($data['link_clicks']      ?? 0),
            ':spend'            => (float) ($data['spend']            ?? 0),
            ':cpc'              => (float) ($data['cpc']              ?? 0),
            ':cpm'              => (float) ($data['cpm']              ?? 0),
            ':ctr'              => (float) ($data['ctr']              ?? 0),
            ':cpp'              => (float) ($data['cpp']              ?? 0),
            ':conversions'      => (int)   ($data['conversions']      ?? 0),
            ':conversion_value' => (float) ($data['conversion_value'] ?? 0),
            ':roas'             => (float) ($data['roas']             ?? 0),
            ':video_views'      => (int)   ($data['video_views']      ?? 0),
            ':video_p25'        => (int)   ($data['video_p25']        ?? 0),
            ':video_p50'        => (int)   ($data['video_p50']        ?? 0),
            ':video_p75'        => (int)   ($data['video_p75']        ?? 0),
            ':video_p100'       => (int)   ($data['video_p100']       ?? 0),
        ]);
    }

    /** Totais agregados por nível para um account+período */
    public function summaryForAccount(int $accountId, string $since, string $until, string $entityType = 'campaign'): array
    {
        return $this->first("
            SELECT
                COALESCE(SUM(spend), 0)            AS total_spend,
                COALESCE(SUM(impressions), 0)       AS total_impressions,
                COALESCE(SUM(reach), 0)             AS total_reach,
                COALESCE(SUM(clicks), 0)            AS total_clicks,
                COALESCE(SUM(link_clicks), 0)       AS total_link_clicks,
                COALESCE(SUM(conversions), 0)       AS total_conversions,
                COALESCE(SUM(conversion_value), 0)  AS total_conversion_value,
                CASE WHEN SUM(clicks) > 0
                     THEN SUM(spend) / SUM(clicks)  ELSE 0 END AS avg_cpc,
                CASE WHEN SUM(impressions) > 0
                     THEN (SUM(spend) / SUM(impressions)) * 1000 ELSE 0 END AS avg_cpm,
                CASE WHEN SUM(clicks) > 0
                     THEN CAST(SUM(clicks) AS FLOAT) / SUM(impressions) ELSE 0 END AS avg_ctr,
                CASE WHEN SUM(spend) > 0
                     THEN SUM(conversion_value) / SUM(spend) ELSE 0 END AS avg_roas
            FROM ad_daily_metrics
            WHERE ad_account_id = :account_id
              AND entity_type   = :entity_type
              AND date BETWEEN :since AND :until
        ", [':account_id' => $accountId, ':entity_type' => $entityType, ':since' => $since, ':until' => $until]) ?? [];
    }

    /** Métricas diárias de um account para gráfico */
    public function dailyForAccount(int $accountId, string $since, string $until, string $entityType = 'campaign'): array
    {
        return $this->all("
            SELECT date,
                   SUM(spend)            AS spend,
                   SUM(impressions)      AS impressions,
                   SUM(clicks)           AS clicks,
                   SUM(conversions)      AS conversions,
                   CASE WHEN SUM(spend) > 0
                        THEN SUM(conversion_value) / SUM(spend) ELSE 0 END AS roas
            FROM ad_daily_metrics
            WHERE ad_account_id = :account_id
              AND entity_type   = :entity_type
              AND date BETWEEN :since AND :until
            GROUP BY date ORDER BY date
        ", [':account_id' => $accountId, ':entity_type' => $entityType, ':since' => $since, ':until' => $until]);
    }

    /** Métricas agregadas por campanha para a tabela do dashboard */
    public function metricsPerCampaign(int $accountId, string $since, string $until): array
    {
        return $this->all("
            SELECT c.id, c.name, c.status, c.objective, c.daily_budget, c.lifetime_budget,
                   COALESCE(SUM(m.spend), 0)           AS spend,
                   COALESCE(SUM(m.impressions), 0)      AS impressions,
                   COALESCE(SUM(m.reach), 0)            AS reach,
                   COALESCE(SUM(m.clicks), 0)           AS clicks,
                   COALESCE(SUM(m.link_clicks), 0)      AS link_clicks,
                   COALESCE(SUM(m.conversions), 0)      AS conversions,
                   COALESCE(SUM(m.conversion_value), 0) AS conversion_value,
                   CASE WHEN SUM(m.clicks) > 0
                        THEN SUM(m.spend) / SUM(m.clicks) ELSE 0 END AS cpc,
                   CASE WHEN SUM(m.impressions) > 0
                        THEN (SUM(m.spend)/SUM(m.impressions))*1000 ELSE 0 END AS cpm,
                   CASE WHEN SUM(m.spend) > 0
                        THEN SUM(m.conversion_value)/SUM(m.spend) ELSE 0 END AS roas
            FROM campaigns c
            LEFT JOIN ad_daily_metrics m ON m.entity_type = 'campaign' AND m.entity_id = c.id
                AND m.date BETWEEN :since AND :until
            WHERE c.ad_account_id = :account_id
            GROUP BY c.id, c.name, c.status, c.objective, c.daily_budget, c.lifetime_budget
            ORDER BY spend DESC
        ", [':account_id' => $accountId, ':since' => $since, ':until' => $until]);
    }

    /** Métricas por conjunto de anúncio de uma campanha */
    public function metricsPerAdSet(int $campaignId, string $since, string $until): array
    {
        return $this->all("
            SELECT s.id, s.name, s.status, s.optimization_goal,
                   s.daily_budget, s.targeting_summary,
                   COALESCE(SUM(m.spend), 0)           AS spend,
                   COALESCE(SUM(m.impressions), 0)      AS impressions,
                   COALESCE(SUM(m.clicks), 0)           AS clicks,
                   COALESCE(SUM(m.link_clicks), 0)      AS link_clicks,
                   COALESCE(SUM(m.conversions), 0)      AS conversions,
                   COALESCE(SUM(m.conversion_value), 0) AS conversion_value,
                   CASE WHEN SUM(m.clicks) > 0
                        THEN SUM(m.spend)/SUM(m.clicks) ELSE 0 END AS cpc,
                   CASE WHEN SUM(m.spend) > 0
                        THEN SUM(m.conversion_value)/SUM(m.spend) ELSE 0 END AS roas
            FROM ad_sets s
            LEFT JOIN ad_daily_metrics m ON m.entity_type = 'adset' AND m.entity_id = s.id
                AND m.date BETWEEN :since AND :until
            WHERE s.campaign_id = :cid
            GROUP BY s.id, s.name, s.status, s.optimization_goal, s.daily_budget, s.targeting_summary
            ORDER BY spend DESC
        ", [':cid' => $campaignId, ':since' => $since, ':until' => $until]);
    }

    /** Métricas por anúncio de um conjunto */
    public function metricsPerAd(int $adSetId, string $since, string $until): array
    {
        return $this->all("
            SELECT a.id, a.name, a.status, a.creative_type,
                   a.headline, a.image_url, a.thumbnail_url, a.call_to_action,
                   COALESCE(SUM(m.spend), 0)           AS spend,
                   COALESCE(SUM(m.impressions), 0)      AS impressions,
                   COALESCE(SUM(m.clicks), 0)           AS clicks,
                   COALESCE(SUM(m.link_clicks), 0)      AS link_clicks,
                   COALESCE(SUM(m.conversions), 0)      AS conversions,
                   COALESCE(SUM(m.conversion_value), 0) AS conversion_value,
                   CASE WHEN SUM(m.clicks) > 0
                        THEN SUM(m.spend)/SUM(m.clicks) ELSE 0 END AS cpc,
                   CASE WHEN SUM(m.spend) > 0
                        THEN SUM(m.conversion_value)/SUM(m.spend) ELSE 0 END AS roas
            FROM ads a
            LEFT JOIN ad_daily_metrics m ON m.entity_type = 'ad' AND m.entity_id = a.id
                AND m.date BETWEEN :since AND :until
            WHERE a.ad_set_id = :sid
            GROUP BY a.id, a.name, a.status, a.creative_type,
                     a.headline, a.image_url, a.thumbnail_url, a.call_to_action
            ORDER BY spend DESC
        ", [':sid' => $adSetId, ':since' => $since, ':until' => $until]);
    }

    /** Todas as métricas de campanha de uma agência (multi-conta) */
    public function metricsPerCampaignForAgency(int $agencyId, string $since, string $until): array
    {
        return $this->all("
            SELECT c.id, c.name, c.status,
                   a.name AS account_name, a.id AS ad_account_id,
                   cl.name AS client_name,
                   COALESCE(SUM(m.spend), 0)            AS spend,
                   COALESCE(SUM(m.impressions), 0)       AS impressions,
                   COALESCE(SUM(m.clicks), 0)            AS clicks,
                   COALESCE(SUM(m.conversions), 0)       AS conversions,
                   CASE WHEN SUM(m.spend) > 0
                        THEN SUM(m.conversion_value)/SUM(m.spend) ELSE 0 END AS roas
            FROM campaigns c
            JOIN ad_accounts a ON a.id = c.ad_account_id AND a.agency_id = :agency_id
            LEFT JOIN clients cl ON cl.id = a.client_id
            LEFT JOIN ad_daily_metrics m ON m.entity_type = 'campaign' AND m.entity_id = c.id
                AND m.date BETWEEN :since AND :until
            GROUP BY c.id, c.name, c.status, a.name, a.id, cl.name
            ORDER BY spend DESC
        ", [':agency_id' => $agencyId, ':since' => $since, ':until' => $until]);
    }
}
