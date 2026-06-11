<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendAgencies extends AbstractMigration
{
    public function up(): void
    {
        // Colunas extras para agências (usadas na página de Configurações)
        $this->execute("
            ALTER TABLE agencies
                ADD COLUMN IF NOT EXISTS email       VARCHAR(255),
                ADD COLUMN IF NOT EXISTS phone       VARCHAR(50),
                ADD COLUMN IF NOT EXISTS website     VARCHAR(255),
                ADD COLUMN IF NOT EXISTS logo_url    VARCHAR(512),
                ADD COLUMN IF NOT EXISTS language    VARCHAR(10) NOT NULL DEFAULT 'pt'
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE agencies
                DROP COLUMN IF EXISTS email,
                DROP COLUMN IF EXISTS phone,
                DROP COLUMN IF EXISTS website,
                DROP COLUMN IF EXISTS logo_url,
                DROP COLUMN IF EXISTS language
        ");
    }
}
