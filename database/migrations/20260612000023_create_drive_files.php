<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDriveFiles extends AbstractMigration
{
    public function up(): void
    {
        // Índice de metadados — a galeria lê daqui (rápido). Bytes ficam só no Drive.
        $this->execute("
            CREATE TABLE IF NOT EXISTS drive_files (
                id               BIGSERIAL    PRIMARY KEY,
                agency_id        BIGINT       NOT NULL,
                client_id        BIGINT       NOT NULL,
                folder_id        BIGINT       REFERENCES drive_folders (id) ON DELETE CASCADE,
                drive_file_id    VARCHAR(255) NOT NULL,
                name             VARCHAR(255) NOT NULL,
                mime_type        VARCHAR(100),
                size_bytes       BIGINT,
                thumbnail_link   TEXT,
                web_view_link    TEXT,
                uploaded_via     VARCHAR(20)  NOT NULL DEFAULT 'portal',
                created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS drive_files_client_folder_idx ON drive_files (client_id, folder_id, created_at)");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS drive_files");
    }
}
