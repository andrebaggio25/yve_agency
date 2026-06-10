<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateContentTables extends AbstractMigration
{
    public function change(): void
    {
        // ── content_plans ──────────────────────────────────────────────────────
        $this->table('content_plans', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',           'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',    'biginteger', ['signed' => false])
            ->addColumn('client_id',    'biginteger', ['signed' => false])
            ->addColumn('title',        'string',     ['limit' => 255])
            ->addColumn('week_start',   'date')
            ->addColumn('week_end',     'date')
            ->addColumn('status',       'string',     ['limit' => 50, 'default' => 'draft'])
            ->addColumn('created_by',   'biginteger', ['signed' => false])
            ->addColumn('sent_at',      'timestamp',  ['null' => true])
            ->addColumn('approved_at',  'timestamp',  ['null' => true])
            ->addColumn('notes',        'text',       ['null' => true])
            ->addColumn('created_at',   'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',   'timestamp',  ['null' => true])
            ->addIndex(['client_id', 'week_start'])
            ->addIndex(['agency_id'])
            ->addIndex(['status'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('client_id', 'clients',  'id', ['delete' => 'CASCADE'])
            ->create();

        // ── content_plan_items ─────────────────────────────────────────────────
        $this->table('content_plan_items', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',               'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('content_plan_id',  'biginteger', ['signed' => false])
            ->addColumn('client_id',        'biginteger', ['signed' => false])
            ->addColumn('publish_date',     'date',       ['null' => true])
            ->addColumn('publish_time',     'time',       ['null' => true])
            ->addColumn('content_type',     'string',     ['limit' => 100, 'null' => true])
            ->addColumn('title',            'string',     ['limit' => 255, 'null' => true])
            ->addColumn('theme',            'string',     ['limit' => 255, 'null' => true])
            ->addColumn('caption',          'text',       ['null' => true])
            ->addColumn('script',           'text',       ['null' => true])
            ->addColumn('cta',              'text',       ['null' => true])
            ->addColumn('drive_url',        'text',       ['null' => true])
            ->addColumn('drive_file_id',    'string',     ['limit' => 255, 'null' => true])
            ->addColumn('drive_file_type',  'string',     ['limit' => 100, 'null' => true])
            ->addColumn('status',           'string',     ['limit' => 50, 'default' => 'draft'])
            ->addColumn('assigned_to',      'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('sort_order',       'integer',    ['default' => 0])
            ->addColumn('created_at',       'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',       'timestamp',  ['null' => true])
            ->addIndex(['content_plan_id'])
            ->addIndex(['client_id'])
            ->addIndex(['status'])
            ->addIndex(['publish_date'])
            ->addForeignKey('content_plan_id', 'content_plans', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('client_id',        'clients',       'id', ['delete' => 'CASCADE'])
            ->create();

        // ── content_feedbacks ──────────────────────────────────────────────────
        $this->table('content_feedbacks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',                   'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('content_plan_item_id', 'biginteger', ['signed' => false])
            ->addColumn('content_plan_id',      'biginteger', ['signed' => false])
            ->addColumn('client_id',            'biginteger', ['signed' => false])
            ->addColumn('user_id',              'biginteger', ['signed' => false])
            ->addColumn('feedback_type',        'string',     ['limit' => 50, 'comment' => 'approved|changes_requested|rejected|comment'])
            ->addColumn('comment',              'text',       ['null' => true])
            ->addColumn('created_at',           'timestamp',  ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['content_plan_item_id'])
            ->addIndex(['content_plan_id'])
            ->addForeignKey('content_plan_item_id', 'content_plan_items', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('content_plan_id',      'content_plans',      'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id',              'users',              'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
