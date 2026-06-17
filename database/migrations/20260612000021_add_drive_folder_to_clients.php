<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDriveFolderToClients extends AbstractMigration
{
    public function up(): void
    {
        // Pasta raiz de cada cliente no Drive da agência (criada pelo app sob a root).
        $this->execute("ALTER TABLE clients ADD COLUMN IF NOT EXISTS drive_folder_id VARCHAR(255)");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE clients DROP COLUMN IF EXISTS drive_folder_id");
    }
}
