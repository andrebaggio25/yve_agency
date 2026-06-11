<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fase 6 — Métricas Orgânicas
 *
 * organic_accounts  — Páginas/perfis conectados (Facebook Page, Instagram Business)
 * organic_posts     — Posts sincronizados com métricas completas
 * organic_daily     — Métricas diárias da conta (seguidores, alcance, impressões)
 */
final class CreateOrganicMetricsTables extends AbstractMigration
{
    public function change(): void
    {
        // ── organic_accounts ──────────────────────────────────────────────────
        $this->table('organic_accounts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',          'biginteger', ['signed' => false])
            ->addColumn('client_id',          'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('platform',           'string',    ['limit' => 20,
                'comment' => 'instagram|facebook|linkedin'])
            ->addColumn('platform_page_id',   'string',    ['limit' => 100,
                'comment' => 'ID da página/perfil na plataforma'])
            ->addColumn('instagram_user_id',  'string',    ['limit' => 100, 'null' => true,
                'comment' => 'Instagram user ID vinculado à página (para Instagram Graph API)'])
            ->addColumn('name',               'string',    ['limit' => 255])
            ->addColumn('username',           'string',    ['limit' => 100, 'null' => true])
            ->addColumn('profile_picture_url','text',      ['null' => true])
            ->addColumn('biography',          'text',      ['null' => true])
            ->addColumn('website',            'string',    ['limit' => 500, 'null' => true])
            ->addColumn('access_token',       'text',
                ['comment' => 'Page access token (longa duração)'])
            ->addColumn('token_expires_at',   'timestamp', ['null' => true])
            ->addColumn('followers_count',    'integer',   ['default' => 0])
            ->addColumn('following_count',    'integer',   ['default' => 0])
            ->addColumn('media_count',        'integer',   ['default' => 0])
            ->addColumn('status',             'string',    ['limit' => 20, 'default' => 'active',
                'comment' => 'active|disconnected|error'])
            ->addColumn('last_synced_at',     'timestamp', ['null' => true])
            ->addColumn('sync_days_back',     'integer',   ['default' => 30])
            ->addColumn('created_by',         'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',         'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',         'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['client_id'])
            ->addIndex(['agency_id', 'platform', 'platform_page_id'], [
                'unique' => true,
                'name'   => 'organic_accounts_unique',
            ])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('client_id', 'clients',  'id', ['delete' => 'SET NULL'])
            ->create();

        // ── organic_posts ─────────────────────────────────────────────────────
        $this->table('organic_posts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                  'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('organic_account_id',  'biginteger', ['signed' => false])
            ->addColumn('platform',            'string',    ['limit' => 20])
            ->addColumn('platform_post_id',    'string',    ['limit' => 100])
            ->addColumn('media_type',          'string',    ['limit' => 30, 'null' => true,
                'comment' => 'IMAGE|VIDEO|CAROUSEL_ALBUM|REEL|STORY'])
            ->addColumn('media_url',           'text',      ['null' => true])
            ->addColumn('thumbnail_url',       'text',      ['null' => true])
            ->addColumn('permalink',           'text',      ['null' => true])
            ->addColumn('caption',             'text',      ['null' => true])
            ->addColumn('posted_at',           'timestamp', ['null' => true])
            // Métricas
            ->addColumn('impressions',         'integer',   ['default' => 0])
            ->addColumn('reach',               'integer',   ['default' => 0])
            ->addColumn('likes',               'integer',   ['default' => 0])
            ->addColumn('comments',            'integer',   ['default' => 0])
            ->addColumn('shares',              'integer',   ['default' => 0])
            ->addColumn('saves',               'integer',   ['default' => 0])
            ->addColumn('video_views',         'integer',   ['default' => 0])
            ->addColumn('video_plays',         'integer',   ['default' => 0])
            ->addColumn('engagement_rate',     'decimal',   ['precision' => 6, 'scale' => 4, 'default' => 0])
            ->addColumn('synced_at',           'timestamp', ['null' => true])
            ->addColumn('created_at',          'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',          'timestamp', ['null' => true])
            ->addIndex(['organic_account_id'])
            ->addIndex(['posted_at'])
            ->addIndex(['organic_account_id', 'platform_post_id'], [
                'unique' => true,
                'name'   => 'organic_posts_unique',
            ])
            ->addForeignKey('organic_account_id', 'organic_accounts', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── organic_daily ─────────────────────────────────────────────────────
        // Snapshot diário da conta (seguidores, alcance, impressões do feed)
        $this->table('organic_daily', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                   'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('organic_account_id',   'biginteger', ['signed' => false])
            ->addColumn('date',                 'date')
            ->addColumn('followers_count',      'integer',   ['default' => 0])
            ->addColumn('followers_gained',     'integer',   ['default' => 0])
            ->addColumn('followers_lost',       'integer',   ['default' => 0])
            ->addColumn('impressions',          'integer',   ['default' => 0])
            ->addColumn('reach',                'integer',   ['default' => 0])
            ->addColumn('profile_views',        'integer',   ['default' => 0])
            ->addColumn('website_clicks',       'integer',   ['default' => 0])
            ->addColumn('posts_count',          'integer',   ['default' => 0])
            ->addColumn('created_at',           'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['organic_account_id', 'date'], [
                'unique' => true,
                'name'   => 'organic_daily_unique',
            ])
            ->addForeignKey('organic_account_id', 'organic_accounts', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
