<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateClickupTables extends AbstractMigration
{
    public function up(): void
    {
        // ── clickup_integrations: uma linha por agência ─────────────────────────
        $this->execute("
            CREATE TABLE IF NOT EXISTS clickup_integrations (
                id               BIGSERIAL PRIMARY KEY,
                agency_id        BIGINT       NOT NULL UNIQUE,
                api_token        TEXT         NOT NULL,
                workspace_id     VARCHAR(50),
                default_list_id  VARCHAR(50)  NOT NULL,
                webhook_token    VARCHAR(64)  NOT NULL UNIQUE,
                webhook_id       VARCHAR(50),
                status_map       JSONB        NOT NULL DEFAULT '{\"todo\":\"to do\",\"in_progress\":\"in progress\",\"review\":\"review\",\"done\":\"complete\"}'::jsonb,
                status           VARCHAR(20)  NOT NULL DEFAULT 'active',
                created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at       TIMESTAMPTZ
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS clickup_integrations_agency_idx ON clickup_integrations (agency_id)");

        // ── tasks: colunas de sincronização ────────────────────────────────────
        $this->execute("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS external_id    VARCHAR(50)");
        $this->execute("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS sync_source    VARCHAR(20)");
        $this->execute("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMPTZ");
        $this->execute("CREATE INDEX IF NOT EXISTS tasks_external_id_idx ON tasks (external_id) WHERE external_id IS NOT NULL");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE tasks DROP COLUMN IF EXISTS last_synced_at");
        $this->execute("ALTER TABLE tasks DROP COLUMN IF EXISTS sync_source");
        $this->execute("ALTER TABLE tasks DROP COLUMN IF EXISTS external_id");
        $this->execute("DROP TABLE IF EXISTS clickup_integrations");
    }
}
