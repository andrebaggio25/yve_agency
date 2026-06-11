<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPortalTokenToClients extends AbstractMigration
{
    public function up(): void
    {
        // portal_token: link de acesso permanente para o cliente
        $this->execute("
            ALTER TABLE clients
            ADD COLUMN IF NOT EXISTS portal_token VARCHAR(64) UNIQUE,
            ADD COLUMN IF NOT EXISTS portal_enabled BOOLEAN NOT NULL DEFAULT true
        ");

        // Gerar tokens para clientes existentes usando md5 duplo para ter 64 chars
        $this->execute("
            UPDATE clients
            SET portal_token = md5(id::text || agency_id::text || created_at::text || random()::text)
                            || md5(random()::text || now()::text)
            WHERE portal_token IS NULL
        ");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE clients DROP COLUMN IF EXISTS portal_token, DROP COLUMN IF EXISTS portal_enabled");
    }
}
