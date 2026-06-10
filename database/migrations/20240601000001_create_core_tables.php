<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCoreTables extends AbstractMigration
{
    public function change(): void
    {
        // ── agencies ─────────────────────────────────────────────────────────
        $this->table('agencies', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',              'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name',            'string',    ['limit' => 255])
            ->addColumn('legal_name',      'string',    ['limit' => 255, 'null' => true])
            ->addColumn('document_number', 'string',    ['limit' => 100, 'null' => true])
            ->addColumn('country',         'string',    ['limit' => 100, 'null' => true, 'default' => 'BR'])
            ->addColumn('currency_code',   'string',    ['limit' => 10,  'default' => 'BRL'])
            ->addColumn('timezone',        'string',    ['limit' => 100, 'default' => 'America/Sao_Paulo'])
            ->addColumn('status',          'string',    ['limit' => 50,  'default' => 'active'])
            ->addColumn('created_at',      'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',      'timestamp', ['null' => true])
            ->create();

        // ── users ─────────────────────────────────────────────────────────────
        $this->table('users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',            'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',     'biginteger', ['signed' => false])
            ->addColumn('name',          'string',    ['limit' => 255])
            ->addColumn('email',         'string',    ['limit' => 255])
            ->addColumn('password_hash', 'string',    ['limit' => 255])
            ->addColumn('phone',         'string',    ['limit' => 50, 'null' => true])
            ->addColumn('avatar',        'string',    ['limit' => 255, 'null' => true])
            ->addColumn('status',        'string',    ['limit' => 50, 'default' => 'active'])
            ->addColumn('last_login_at', 'timestamp', ['null' => true])
            ->addColumn('created_at',    'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',    'timestamp', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['agency_id'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // ── password_reset_tokens ─────────────────────────────────────────────
        $this->table('password_reset_tokens', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',         'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id',    'biginteger', ['signed' => false])
            ->addColumn('token',      'string',    ['limit' => 100])
            ->addColumn('expires_at', 'timestamp')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── roles ─────────────────────────────────────────────────────────────
        $this->table('roles', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',          'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',   'biginteger', ['signed' => false, 'null' => true, 'comment' => 'NULL = role global do sistema'])
            ->addColumn('name',        'string',    ['limit' => 100])
            ->addColumn('slug',        'string',    ['limit' => 100])
            ->addColumn('description', 'text',      ['null' => true])
            ->addColumn('created_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',  'timestamp', ['null' => true])
            ->addIndex(['slug', 'agency_id'], ['unique' => true, 'name' => 'roles_slug_agency_unique'])
            ->addIndex(['agency_id'])
            ->create();

        // ── permissions ───────────────────────────────────────────────────────
        $this->table('permissions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',          'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name',        'string',    ['limit' => 150])
            ->addColumn('slug',        'string',    ['limit' => 150])
            ->addColumn('module',      'string',    ['limit' => 100])
            ->addColumn('description', 'text',      ['null' => true])
            ->addColumn('created_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->addIndex(['module'])
            ->create();

        // ── role_permissions ──────────────────────────────────────────────────
        $this->table('role_permissions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',            'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('role_id',       'biginteger', ['signed' => false])
            ->addColumn('permission_id', 'biginteger', ['signed' => false])
            ->addColumn('created_at',    'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['role_id', 'permission_id'], ['unique' => true, 'name' => 'role_perm_unique'])
            ->addForeignKey('role_id',       'roles',       'id', ['delete' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── user_roles ────────────────────────────────────────────────────────
        $this->table('user_roles', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',         'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id',    'biginteger', ['signed' => false])
            ->addColumn('role_id',    'biginteger', ['signed' => false])
            ->addColumn('created_at', 'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'role_id'], ['unique' => true, 'name' => 'user_role_unique'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── clients ───────────────────────────────────────────────────────────
        $this->table('clients', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',              'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',       'biginteger', ['signed' => false])
            ->addColumn('name',            'string',    ['limit' => 255])
            ->addColumn('legal_name',      'string',    ['limit' => 255, 'null' => true])
            ->addColumn('document_type',   'string',    ['limit' => 50, 'null' => true])
            ->addColumn('document_number', 'string',    ['limit' => 100, 'null' => true])
            ->addColumn('country',         'string',    ['limit' => 100, 'null' => true, 'default' => 'BR'])
            ->addColumn('state',           'string',    ['limit' => 100, 'null' => true])
            ->addColumn('city',            'string',    ['limit' => 100, 'null' => true])
            ->addColumn('address',         'text',      ['null' => true])
            ->addColumn('postal_code',     'string',    ['limit' => 50, 'null' => true])
            ->addColumn('language',        'string',    ['limit' => 20, 'default' => 'pt-BR'])
            ->addColumn('timezone',        'string',    ['limit' => 100, 'default' => 'America/Sao_Paulo'])
            ->addColumn('currency_code',   'string',    ['limit' => 10, 'default' => 'BRL'])
            ->addColumn('segment',         'string',    ['limit' => 150, 'null' => true])
            ->addColumn('niche',           'string',    ['limit' => 150, 'null' => true])
            ->addColumn('status',          'string',    ['limit' => 50, 'default' => 'active'])
            ->addColumn('start_date',      'date',      ['null' => true])
            ->addColumn('manager_user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',      'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',      'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['status'])
            ->addIndex(['agency_id', 'status'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'RESTRICT'])
            ->create();

        // ── client_contacts ───────────────────────────────────────────────────
        $this->table('client_contacts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',          'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('client_id',   'biginteger', ['signed' => false])
            ->addColumn('name',        'string',     ['limit' => 255])
            ->addColumn('role',        'string',     ['limit' => 100, 'null' => true])
            ->addColumn('email',       'string',     ['limit' => 255, 'null' => true])
            ->addColumn('whatsapp',    'string',     ['limit' => 50, 'null' => true])
            ->addColumn('language',    'string',     ['limit' => 20, 'null' => true])
            ->addColumn('channel',     'string',     ['limit' => 50, 'null' => true, 'comment' => 'whatsapp|email'])
            ->addColumn('type',        'string',     ['limit' => 50, 'null' => true, 'comment' => 'admin|approver|financial|technical'])
            ->addColumn('is_primary',  'boolean',    ['default' => false])
            ->addColumn('created_at',  'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['client_id'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── client_user_access ────────────────────────────────────────────────
        $this->table('client_user_access', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',           'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id',      'biginteger', ['signed' => false])
            ->addColumn('client_id',    'biginteger', ['signed' => false])
            ->addColumn('access_level', 'string',     ['limit' => 100, 'default' => 'standard'])
            ->addColumn('created_at',   'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'client_id'], ['unique' => true, 'name' => 'user_client_access_unique'])
            ->addIndex(['client_id'])
            ->addForeignKey('user_id',   'users',   'id', ['delete' => 'CASCADE'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── client_marketing_profiles ─────────────────────────────────────────
        $this->table('client_marketing_profiles', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('client_id',         'biginteger', ['signed' => false])
            ->addColumn('target_audience',   'text',       ['null' => true])
            ->addColumn('persona',           'text',       ['null' => true])
            ->addColumn('main_offer',        'text',       ['null' => true])
            ->addColumn('average_ticket',    'decimal',    ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('differentials',     'text',       ['null' => true])
            ->addColumn('tone_of_voice',     'text',       ['null' => true])
            ->addColumn('allowed_promises',  'text',       ['null' => true])
            ->addColumn('forbidden_promises','text',       ['null' => true])
            ->addColumn('forbidden_words',   'text',       ['null' => true])
            ->addColumn('competitors',       'text',       ['null' => true])
            ->addColumn('website_url',       'string',     ['limit' => 255, 'null' => true])
            ->addColumn('instagram_url',     'string',     ['limit' => 255, 'null' => true])
            ->addColumn('facebook_url',      'string',     ['limit' => 255, 'null' => true])
            ->addColumn('google_business_url','string',    ['limit' => 255, 'null' => true])
            ->addColumn('landing_pages',     'text',       ['null' => true])
            ->addColumn('created_at',        'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',        'timestamp',  ['null' => true])
            ->addIndex(['client_id'], ['unique' => true])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── client_financial_profiles ─────────────────────────────────────────
        $this->table('client_financial_profiles', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('client_id',         'biginteger', ['signed' => false])
            ->addColumn('monthly_amount',    'decimal',    ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('currency_code',     'string',     ['limit' => 10, 'null' => true])
            ->addColumn('due_day',           'integer',    ['null' => true])
            ->addColumn('payment_method',    'string',     ['limit' => 100, 'null' => true])
            ->addColumn('tax_rate',          'decimal',    ['precision' => 8, 'scale' => 4, 'null' => true])
            ->addColumn('discount_amount',   'decimal',    ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('contract_active',   'boolean',    ['default' => false])
            ->addColumn('renewal_date',      'date',       ['null' => true])
            ->addColumn('notes',             'text',       ['null' => true])
            ->addColumn('created_at',        'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',        'timestamp',  ['null' => true])
            ->addIndex(['client_id'], ['unique' => true])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── client_integrations ───────────────────────────────────────────────
        $this->table('client_integrations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                    'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('client_id',             'biginteger', ['signed' => false])
            ->addColumn('meta_business_id',      'string',     ['limit' => 100, 'null' => true])
            ->addColumn('meta_ad_account_id',    'string',     ['limit' => 100, 'null' => true])
            ->addColumn('meta_pixel_id',         'string',     ['limit' => 100, 'null' => true])
            ->addColumn('meta_access_token_enc', 'text',       ['null' => true, 'comment' => 'token criptografado'])
            ->addColumn('facebook_page_id',      'string',     ['limit' => 100, 'null' => true])
            ->addColumn('instagram_business_id', 'string',     ['limit' => 100, 'null' => true])
            ->addColumn('google_drive_root_url', 'text',       ['null' => true])
            ->addColumn('google_drive_folder_id','string',     ['limit' => 255, 'null' => true])
            ->addColumn('whatsapp_phone',        'string',     ['limit' => 50, 'null' => true])
            ->addColumn('evolution_instance_id', 'string',     ['limit' => 255, 'null' => true])
            ->addColumn('created_at',            'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',            'timestamp',  ['null' => true])
            ->addIndex(['client_id'], ['unique' => true])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── currencies ────────────────────────────────────────────────────────
        $this->table('currencies', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',             'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('code',           'string',     ['limit' => 10])
            ->addColumn('symbol',         'string',     ['limit' => 10])
            ->addColumn('name',           'string',     ['limit' => 100])
            ->addColumn('decimal_places', 'integer',    ['default' => 2])
            ->addColumn('created_at',     'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['code'], ['unique' => true])
            ->create();

        // ── activity_logs ─────────────────────────────────────────────────────
        $this->table('activity_logs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',            'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',     'biginteger', ['signed' => false])
            ->addColumn('user_id',       'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_id',     'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('action',        'string',     ['limit' => 150])
            ->addColumn('module',        'string',     ['limit' => 100])
            ->addColumn('ip_address',    'string',     ['limit' => 100, 'null' => true])
            ->addColumn('user_agent',    'text',       ['null' => true])
            ->addColumn('metadata_json', 'text',       ['null' => true])
            ->addColumn('created_at',    'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['agency_id'])
            ->addIndex(['user_id'])
            ->addIndex(['client_id'])
            ->addIndex(['action'])
            ->addIndex(['created_at'])
            ->create();

        // ── jobs (fila) ───────────────────────────────────────────────────────
        $this->table('jobs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',           'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('queue',        'string',     ['limit' => 100, 'default' => 'default'])
            ->addColumn('payload',      'text')
            ->addColumn('available_at', 'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('reserved_at',  'timestamp',  ['null' => true])
            ->addColumn('attempts',     'integer',    ['default' => 0])
            ->addColumn('max_attempts', 'integer',    ['default' => 3])
            ->addColumn('status',       'string',     ['limit' => 50, 'default' => 'pending'])
            ->addColumn('last_error',   'text',       ['null' => true])
            ->addColumn('created_at',   'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',   'timestamp',  ['null' => true])
            ->addIndex(['status', 'available_at'])
            ->addIndex(['queue'])
            ->create();
    }
}
