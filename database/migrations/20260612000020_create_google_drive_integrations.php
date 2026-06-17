<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGoogleDriveIntegrations extends AbstractMigration
{
    public function up(): void
    {
        // Uma linha por agência: conexão OAuth + pasta raiz criada pelo app.
        $this->execute("
            CREATE TABLE IF NOT EXISTS google_drive_integrations (
                id                BIGSERIAL    PRIMARY KEY,
                agency_id         BIGINT       NOT NULL UNIQUE,
                access_token      TEXT,
                refresh_token     TEXT         NOT NULL,
                token_expires_at  TIMESTAMPTZ,
                root_folder_id    VARCHAR(255),
                connected_email   VARCHAR(255),
                status            VARCHAR(20)  NOT NULL DEFAULT 'active',
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at        TIMESTAMPTZ
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS gdrive_integrations_agency_idx ON google_drive_integrations (agency_id)");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS google_drive_integrations");
    }
}
