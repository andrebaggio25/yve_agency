<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotificationTables extends AbstractMigration
{
    public function change(): void
    {
        // ── whatsapp column on clients ────────────────────────────────────────
        $this->table('clients')
            ->addColumn('whatsapp', 'string', ['limit' => 30, 'null' => true, 'after' => 'language'])
            ->addColumn('notify_whatsapp', 'boolean', ['default' => true, 'null' => false, 'after' => 'whatsapp'])
            ->addColumn('notify_email',    'boolean', ['default' => true, 'null' => false, 'after' => 'notify_whatsapp'])
            ->update();

        // ── whatsapp_instances ────────────────────────────────────────────────
        $this->table('whatsapp_instances')
            ->addColumn('agency_id',     'integer',  ['null' => false])
            ->addColumn('instance_name', 'string',   ['limit' => 100, 'null' => false])
            ->addColumn('base_url',      'string',   ['limit' => 255, 'null' => false])
            ->addColumn('api_key',       'string',   ['limit' => 255, 'null' => false])
            ->addColumn('status',        'string',   ['limit' => 30, 'default' => 'disconnected'])
            ->addColumn('phone_number',  'string',   ['limit' => 30, 'null' => true])
            ->addColumn('created_at',    'datetime', ['null' => false])
            ->addColumn('updated_at',    'datetime', ['null' => false])
            ->addIndex(['agency_id'], ['unique' => true])
            ->create();

        // ── notification_jobs (queue) ─────────────────────────────────────────
        $this->table('notification_jobs')
            ->addColumn('agency_id',    'integer',  ['null' => false])
            ->addColumn('channel',      'string',   ['limit' => 20, 'null' => false, 'comment' => 'whatsapp|email'])
            ->addColumn('recipient',    'string',   ['limit' => 255, 'null' => false, 'comment' => 'phone or email'])
            ->addColumn('template',     'string',   ['limit' => 100, 'null' => false])
            ->addColumn('locale',       'string',   ['limit' => 5, 'default' => 'pt'])
            ->addColumn('payload',      'text',     ['null' => false, 'comment' => 'JSON vars for template'])
            ->addColumn('status',       'string',   ['limit' => 20, 'default' => 'pending', 'comment' => 'pending|sent|failed'])
            ->addColumn('attempts',     'integer',  ['default' => 0])
            ->addColumn('last_error',   'text',     ['null' => true])
            ->addColumn('next_try_at',  'datetime', ['null' => true])
            ->addColumn('sent_at',      'datetime', ['null' => true])
            ->addColumn('created_at',   'datetime', ['null' => false])
            ->addIndex(['status', 'next_try_at'])
            ->addIndex(['agency_id'])
            ->create();

        // ── notifications (in-app) ────────────────────────────────────────────
        $this->table('notifications')
            ->addColumn('agency_id',   'integer',  ['null' => false])
            ->addColumn('user_id',     'integer',  ['null' => false])
            ->addColumn('type',        'string',   ['limit' => 50, 'null' => false])
            ->addColumn('title',       'string',   ['limit' => 255, 'null' => false])
            ->addColumn('body',        'text',     ['null' => true])
            ->addColumn('action_url',  'string',   ['limit' => 255, 'null' => true])
            ->addColumn('read_at',     'datetime', ['null' => true])
            ->addColumn('created_at',  'datetime', ['null' => false])
            ->addIndex(['user_id', 'read_at'])
            ->addIndex(['agency_id'])
            ->create();
    }
}
