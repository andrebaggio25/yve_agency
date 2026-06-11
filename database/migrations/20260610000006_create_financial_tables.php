<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fase 4 — Módulo Financeiro
 *
 * Tabelas: contracts, invoices, invoice_items, payments
 */
final class CreateFinancialTables extends AbstractMigration
{
    public function change(): void
    {
        // ── contracts ─────────────────────────────────────────────────────────
        $this->table('contracts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',            'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',     'biginteger', ['signed' => false])
            ->addColumn('client_id',     'biginteger', ['signed' => false])
            ->addColumn('title',         'string',    ['limit' => 255])
            ->addColumn('description',   'text',      ['null' => true])
            ->addColumn('value',         'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('currency_code', 'string',    ['limit' => 10, 'default' => 'BRL'])
            ->addColumn('status',        'string',    ['limit' => 30, 'default' => 'draft',
                'comment' => 'draft|active|expired|cancelled'])
            ->addColumn('start_date',    'date',      ['null' => true])
            ->addColumn('end_date',      'date',      ['null' => true])
            ->addColumn('signed_at',     'timestamp', ['null' => true])
            ->addColumn('recurring',     'boolean',   ['default' => false])
            ->addColumn('recurrence',    'string',    ['limit' => 30, 'null' => true,
                'comment' => 'monthly|quarterly|semiannual|annual'])
            ->addColumn('notes',         'text',      ['null' => true])
            ->addColumn('created_by',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',    'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',    'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['client_id'])
            ->addIndex(['status'])
            ->addIndex(['agency_id', 'status'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('client_id', 'clients',  'id', ['delete' => 'RESTRICT'])
            ->create();

        // ── invoices ──────────────────────────────────────────────────────────
        $this->table('invoices', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',             'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',      'biginteger', ['signed' => false])
            ->addColumn('client_id',      'biginteger', ['signed' => false])
            ->addColumn('contract_id',    'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('invoice_number', 'string',    ['limit' => 50])
            ->addColumn('title',          'string',    ['limit' => 255])
            ->addColumn('status',         'string',    ['limit' => 30, 'default' => 'draft',
                'comment' => 'draft|sent|paid|overdue|cancelled|partial'])
            ->addColumn('subtotal',       'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('discount',       'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('tax',            'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('total',          'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('amount_paid',    'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('currency_code',  'string',    ['limit' => 10, 'default' => 'BRL'])
            ->addColumn('due_date',       'date',      ['null' => true])
            ->addColumn('paid_at',        'timestamp', ['null' => true])
            ->addColumn('notes',          'text',      ['null' => true])
            ->addColumn('created_by',     'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',     'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',     'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['client_id'])
            ->addIndex(['status'])
            ->addIndex(['invoice_number', 'agency_id'], ['unique' => true, 'name' => 'invoices_number_agency_unique'])
            ->addForeignKey('agency_id',   'agencies',  'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('client_id',   'clients',   'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('contract_id', 'contracts', 'id', ['delete' => 'SET NULL'])
            ->create();

        // ── invoice_items ─────────────────────────────────────────────────────
        $this->table('invoice_items', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',          'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('invoice_id',  'biginteger', ['signed' => false])
            ->addColumn('description', 'string',    ['limit' => 500])
            ->addColumn('quantity',    'decimal',   ['precision' => 10, 'scale' => 3, 'default' => '1.000'])
            ->addColumn('unit_price',  'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('total_price', 'decimal',   ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('sort_order',  'integer',   ['default' => 0])
            ->addColumn('created_at',  'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ── payments ──────────────────────────────────────────────────────────
        $this->table('payments', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',             'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('agency_id',      'biginteger', ['signed' => false])
            ->addColumn('invoice_id',     'biginteger', ['signed' => false])
            ->addColumn('amount',         'decimal',   ['precision' => 12, 'scale' => 2])
            ->addColumn('payment_method', 'string',    ['limit' => 50, 'default' => 'other',
                'comment' => 'pix|boleto|credit_card|bank_transfer|cash|other'])
            ->addColumn('payment_date',   'date')
            ->addColumn('reference',      'string',    ['limit' => 255, 'null' => true])
            ->addColumn('notes',          'text',      ['null' => true])
            ->addColumn('created_by',     'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at',     'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',     'timestamp', ['null' => true])
            ->addIndex(['agency_id'])
            ->addIndex(['invoice_id'])
            ->addForeignKey('agency_id',  'agencies', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }
}
