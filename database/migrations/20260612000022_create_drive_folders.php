<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDriveFolders extends AbstractMigration
{
    public function up(): void
    {
        // Subpastas criadas pelo app (navegação por cliente). parent_id NULL = raiz do cliente.
        $this->execute("
            CREATE TABLE IF NOT EXISTS drive_folders (
                id               BIGSERIAL    PRIMARY KEY,
                agency_id        BIGINT       NOT NULL,
                client_id        BIGINT       NOT NULL,
                parent_id        BIGINT       REFERENCES drive_folders (id) ON DELETE CASCADE,
                drive_folder_id  VARCHAR(255) NOT NULL,
                name             VARCHAR(255) NOT NULL,
                created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ");
        $this->execute("CREATE INDEX IF NOT EXISTS drive_folders_client_parent_idx ON drive_folders (client_id, parent_id)");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS drive_folders");
    }
}
