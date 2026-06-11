<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fase 5 — Tráfego Pago
 *
 * ad_accounts   — conexão com Meta/Google/TikTok por agência/cliente
 * campaigns     — campanhas importadas
 * ad_sets       — conjuntos de anúncio
 * ads           — anúncios individuais (com info de creative)
 * ad_daily_metrics — métricas diárias (polimórficas: campaign|adset|ad)
 * ai_insights   — insights gerados por IA (Phase 5B)
 * ads_actions   — solicitações de ação em campanhas (Phase 5B)
 */
final class CreateTrafficTables extends AbstractMigration
{
    public function change(): void
    {
        // ── ad_accounts ───────────────────────────────────────────────────────
        $this->table('ad_accounts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',          'biginteger', ['signed' => false])
            ->addColumn('client_id',          'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('platform',           'string',    ['limit' => 20, 'default' => 'meta',
                'comment' => 'meta|google|tiktok'])
            ->addColumn('platform_account_id','string',    ['limit' => 100,
                'comment' => 'Meta: act_123456789'])
            ->addColumn('name',               'string',    ['limit' => 255])
            ->addColumn('currency',           'string',    ['limit' => 10, 'default' => 'BRL'])
            ->addColumn('access_token',       'text')
            ->addColumn('token_type',         'string',    ['limit' => 20, 'default' => 'user',
                'comment' => 'user|system'])
            ->addColumn('token_expires_at',   'timestamp', ['null' => true])
            ->addColumn('status',             'string',    ['limit' => 20, 'default' => 'active',
                'comment' => 'active|disconnected|error'])
            ->addColumn('last_synced_at',     'timestamp', ['null' => true])
            ->addColumn('sync_days_back',     'integer',   ['default' => 30,
                'comment' => 'how many days back to sync metrics'])
            ->addColumn('created_by',         'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',         'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',         'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['client_id'])
            ->addIndex(['agency_id', 'platform', 'platform_account_id'], [
                'unique' => true,
                'name'   => 'ad_accounts_agency_platform_unique',
            ])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('client_id', 'clients',  'id', ['delete' => 'SET NULL'])
            ->create();

        // ── campaigns ─────────────────────────────────────────────────────────
        $this->table('campaigns', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',              'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('ad_account_id',   'biginteger', ['signed' => false])
            ->addColumn('platform',        'string',    ['limit' => 20])
            ->addColumn('platform_id',     'string',    ['limit' => 100])
            ->addColumn('name',            'string',    ['limit' => 500])
            ->addColumn('status',          'string',    ['limit' => 30,
                'comment' => 'ACTIVE|PAUSED|ARCHIVED|DELETED'])
            ->addColumn('objective',       'string',    ['limit' => 100, 'null' => true])
            ->addColumn('buying_type',     'string',    ['limit' => 50,  'null' => true])
            ->addColumn('daily_budget',    'decimal',   ['precision' => 14, 'scale' => 2, 'null' => true])
            ->addColumn('lifetime_budget', 'decimal',   ['precision' => 14, 'scale' => 2, 'null' => true])
            ->addColumn('start_time',      'timestamp', ['null' => true])
            ->addColumn('stop_time',       'timestamp', ['null' => true])
            ->addColumn('synced_at',       'timestamp', ['null' => true])
            ->addColumn('created_at',      'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',      'timestamp', ['null' => true])
            ->addIndex(['ad_account_id'])
            ->addIndex(['status'])
            ->addIndex(['ad_account_id', 'platform_id'], [
                'unique' => true,
                'name'   => 'campaigns_account_platform_unique',
            ])
            ->addForeignKey('ad_account_id', 'ad_accounts', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── ad_sets ───────────────────────────────────────────────────────────
        $this->table('ad_sets', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',               'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('campaign_id',      'biginteger', ['signed' => false])
            ->addColumn('platform_id',      'string',    ['limit' => 100])
            ->addColumn('name',             'string',    ['limit' => 500])
            ->addColumn('status',           'string',    ['limit' => 30])
            ->addColumn('daily_budget',     'decimal',   ['precision' => 14, 'scale' => 2, 'null' => true])
            ->addColumn('lifetime_budget',  'decimal',   ['precision' => 14, 'scale' => 2, 'null' => true])
            ->addColumn('optimization_goal','string',    ['limit' => 100, 'null' => true])
            ->addColumn('billing_event',    'string',    ['limit' => 100, 'null' => true])
            ->addColumn('bid_strategy',     'string',    ['limit' => 100, 'null' => true])
            ->addColumn('bid_amount',       'decimal',   ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('targeting_summary','text',      ['null' => true,
                'comment' => 'JSON resumido de segmentação'])
            ->addColumn('start_time',       'timestamp', ['null' => true])
            ->addColumn('stop_time',        'timestamp', ['null' => true])
            ->addColumn('synced_at',        'timestamp', ['null' => true])
            ->addColumn('created_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',       'timestamp', ['null' => true])
            ->addIndex(['campaign_id'])
            ->addIndex(['campaign_id', 'platform_id'], [
                'unique' => true,
                'name'   => 'ad_sets_campaign_platform_unique',
            ])
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── ads ───────────────────────────────────────────────────────────────
        $this->table('ads', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',              'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('ad_set_id',       'biginteger', ['signed' => false])
            ->addColumn('platform_id',     'string',    ['limit' => 100])
            ->addColumn('name',            'string',    ['limit' => 500])
            ->addColumn('status',          'string',    ['limit' => 30])
            ->addColumn('creative_type',   'string',    ['limit' => 50, 'null' => true,
                'comment' => 'IMAGE|VIDEO|CAROUSEL|DYNAMIC'])
            ->addColumn('headline',        'string',    ['limit' => 500, 'null' => true])
            ->addColumn('body',            'text',      ['null' => true])
            ->addColumn('image_url',       'text',      ['null' => true])
            ->addColumn('thumbnail_url',   'text',      ['null' => true])
            ->addColumn('call_to_action',  'string',    ['limit' => 100, 'null' => true])
            ->addColumn('destination_url', 'text',      ['null' => true])
            ->addColumn('synced_at',       'timestamp', ['null' => true])
            ->addColumn('created_at',      'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',      'timestamp', ['null' => true])
            ->addIndex(['ad_set_id'])
            ->addIndex(['ad_set_id', 'platform_id'], [
                'unique' => true,
                'name'   => 'ads_adset_platform_unique',
            ])
            ->addForeignKey('ad_set_id', 'ad_sets', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── ad_daily_metrics ──────────────────────────────────────────────────
        // Polimórfica: entity_type + entity_id identifica campaign/adset/ad
        $this->table('ad_daily_metrics', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',               'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('ad_account_id',    'biginteger', ['signed' => false,
                'comment' => 'desnormalizado para queries rápidas por conta'])
            ->addColumn('entity_type',      'string',    ['limit' => 10,
                'comment' => 'campaign|adset|ad'])
            ->addColumn('entity_id',        'biginteger', ['signed' => false])
            ->addColumn('date',             'date')
            ->addColumn('platform',         'string',    ['limit' => 20, 'default' => 'meta'])
            // volume
            ->addColumn('impressions',      'biginteger', ['default' => 0])
            ->addColumn('reach',            'biginteger', ['default' => 0])
            ->addColumn('frequency',        'decimal',   ['precision' => 10, 'scale' => 4, 'default' => '0'])
            ->addColumn('clicks',           'biginteger', ['default' => 0,
                'comment' => 'todos os cliques'])
            ->addColumn('link_clicks',      'biginteger', ['default' => 0,
                'comment' => 'cliques no link (outbound)'])
            // custo
            ->addColumn('spend',            'decimal',   ['precision' => 14, 'scale' => 2, 'default' => '0'])
            ->addColumn('cpc',              'decimal',   ['precision' => 10, 'scale' => 4, 'default' => '0'])
            ->addColumn('cpm',              'decimal',   ['precision' => 10, 'scale' => 4, 'default' => '0'])
            ->addColumn('ctr',              'decimal',   ['precision' => 10, 'scale' => 6, 'default' => '0',
                'comment' => '0.0 a 1.0'])
            ->addColumn('cpp',              'decimal',   ['precision' => 10, 'scale' => 4, 'default' => '0',
                'comment' => 'custo por resultado'])
            // conversão
            ->addColumn('conversions',      'integer',   ['default' => 0])
            ->addColumn('conversion_value', 'decimal',   ['precision' => 14, 'scale' => 2, 'default' => '0'])
            ->addColumn('roas',             'decimal',   ['precision' => 10, 'scale' => 4, 'default' => '0'])
            // video
            ->addColumn('video_views',      'biginteger', ['default' => 0])
            ->addColumn('video_p25',        'biginteger', ['default' => 0])
            ->addColumn('video_p50',        'biginteger', ['default' => 0])
            ->addColumn('video_p75',        'biginteger', ['default' => 0])
            ->addColumn('video_p100',       'biginteger', ['default' => 0])
            ->addColumn('created_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',       'timestamp', ['null' => true])
            ->addIndex(['ad_account_id', 'date'])
            ->addIndex(['entity_type', 'entity_id', 'date'], [
                'unique' => true,
                'name'   => 'ad_metrics_entity_date_unique',
            ])
            ->create();

        // ── ai_insights (Phase 5B — schema criado agora) ──────────────────────
        $this->table('ai_insights', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',               'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',        'biginteger', ['signed' => false])
            ->addColumn('client_id',        'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('ad_account_id',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('type',             'string',    ['limit' => 50,
                'comment' => 'performance_summary|alert|recommendation|report'])
            ->addColumn('period_start',     'date',      ['null' => true])
            ->addColumn('period_end',       'date',      ['null' => true])
            ->addColumn('content',          'text')
            ->addColumn('metrics_snapshot', 'text',      ['null' => true,
                'comment' => 'JSON snapshot das métricas usadas'])
            ->addColumn('ai_provider',      'string',    ['limit' => 20, 'null' => true,
                'comment' => 'openai|claude'])
            ->addColumn('model',            'string',    ['limit' => 100, 'null' => true])
            ->addColumn('created_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['agency_id'])
            ->addIndex(['ad_account_id'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── ads_actions (Phase 5B — schema criado agora) ──────────────────────
        $this->table('ads_actions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',             'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',      'biginteger', ['signed' => false])
            ->addColumn('ad_account_id',  'biginteger', ['signed' => false])
            ->addColumn('campaign_id',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('ad_set_id',      'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('ad_id',          'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('action_type',    'string',    ['limit' => 60,
                'comment' => 'pause|resume|increase_budget|decrease_budget|test_creative|archive'])
            ->addColumn('description',    'text')
            ->addColumn('justification',  'text',      ['null' => true])
            ->addColumn('current_value',  'string',    ['limit' => 255, 'null' => true])
            ->addColumn('proposed_value', 'string',    ['limit' => 255, 'null' => true])
            ->addColumn('status',         'string',    ['limit' => 20, 'default' => 'pending',
                'comment' => 'pending|approved|rejected|executed|failed'])
            ->addColumn('ai_generated',   'boolean',   ['default' => false])
            ->addColumn('requested_by',   'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('approved_by',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('approved_at',    'timestamp', ['null' => true])
            ->addColumn('executed_at',    'timestamp', ['null' => true])
            ->addColumn('error_message',  'text',      ['null' => true])
            ->addColumn('created_at',     'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',     'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['ad_account_id'])
            ->addIndex(['status'])
            ->addForeignKey('agency_id',     'agencies',    'id', ['delete' => 'CASCADE'])
            ->addForeignKey('ad_account_id', 'ad_accounts', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
