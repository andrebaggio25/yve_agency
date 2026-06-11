<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAutomationTables extends AbstractMigration
{
    public function up(): void
    {
        // ── automation_rules: config por agência de cada automação ──────────────
        // Lida pelo scheduler (bin/scheduler.php e /queue/scheduler).
        $this->execute("
            CREATE TABLE IF NOT EXISTS automation_rules (
                id              BIGSERIAL PRIMARY KEY,
                agency_id       BIGINT       NOT NULL,
                automation_key  VARCHAR(100) NOT NULL,
                name            VARCHAR(150),
                status          VARCHAR(20)  NOT NULL DEFAULT 'inactive',
                frequency       VARCHAR(20),
                scheduled_day   VARCHAR(20),
                scheduled_time  VARCHAR(8)   DEFAULT '08:00:00',
                channels        JSONB,
                last_run_at     TIMESTAMPTZ,
                next_run_at     TIMESTAMPTZ,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ,
                CONSTRAINT automation_rules_agency_key_unique UNIQUE (agency_id, automation_key)
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS automation_rules_due_idx ON automation_rules (status, next_run_at)");

        // ── client_automation_settings: override opt-in por cliente ─────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS client_automation_settings (
                id              BIGSERIAL PRIMARY KEY,
                agency_id       BIGINT       NOT NULL,
                client_id       BIGINT       NOT NULL,
                automation_key  VARCHAR(100) NOT NULL,
                enabled         BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ,
                CONSTRAINT client_automation_settings_unique UNIQUE (client_id, automation_key)
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS client_automation_settings_agency_idx ON client_automation_settings (agency_id, automation_key)");

        // ── automation_log: idempotência + auditoria ────────────────────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS automation_log (
                id              BIGSERIAL PRIMARY KEY,
                agency_id       BIGINT       NOT NULL,
                client_id       BIGINT,
                automation_key  VARCHAR(100) NOT NULL,
                dedupe_key      VARCHAR(191) NOT NULL,
                channel         VARCHAR(20),
                status          VARCHAR(20)  NOT NULL DEFAULT 'done',
                detail          TEXT,
                created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT automation_log_dedupe_unique UNIQUE (automation_key, dedupe_key)
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS automation_log_agency_idx ON automation_log (agency_id, automation_key)");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS automation_log");
        $this->execute("DROP TABLE IF EXISTS client_automation_settings");
        $this->execute("DROP TABLE IF EXISTS automation_rules");
    }
}
