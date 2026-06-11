<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adiciona suporte a super admin de plataforma (multi-tenant SaaS).
 *
 * - users: is_platform_admin + agency_id nullable
 * - agencies: slug gerado automaticamente
 * - platform_settings: configurações globais (Evolution API, SMTP, etc.)
 * - whatsapp_instances: webhook_token, phone_connected, name; base_url/api_key tornam nullable
 */
final class AddPlatformAdmin extends AbstractMigration
{
    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // ── users ─────────────────────────────────────────────────────────────
        // 1. Tornar agency_id nullable (platform admin não tem agência)
        //    Primeiro dropar o FK constraint, depois alterar a coluna
        $fkName = $this->findFkName($pdo, 'users', 'agency_id');
        if ($fkName) {
            $pdo->exec("ALTER TABLE users DROP CONSTRAINT \"{$fkName}\"");
        }
        $pdo->exec("ALTER TABLE users ALTER COLUMN agency_id DROP NOT NULL");
        // Re-adicionar FK com suporte a NULL
        $pdo->exec("
            ALTER TABLE users
            ADD CONSTRAINT users_agency_id_fkey
            FOREIGN KEY (agency_id) REFERENCES agencies(id)
            ON DELETE RESTRICT ON UPDATE CASCADE
            DEFERRABLE INITIALLY DEFERRED
        ");

        // 2. Adicionar is_platform_admin
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_platform_admin BOOLEAN NOT NULL DEFAULT FALSE");

        // ── agencies ─────────────────────────────────────────────────────────
        $pdo->exec("ALTER TABLE agencies ADD COLUMN IF NOT EXISTS slug VARCHAR(100)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS agencies_slug_unique ON agencies (slug) WHERE slug IS NOT NULL");

        // Gerar slug para agências existentes
        $agencies = $pdo->query("SELECT id, name FROM agencies WHERE slug IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $stmt     = $pdo->prepare("UPDATE agencies SET slug = :slug WHERE id = :id");
        foreach ($agencies as $agency) {
            $stmt->execute([':slug' => $this->generateSlug($pdo, $agency['name'], (int)$agency['id']), ':id' => $agency['id']]);
        }

        // ── platform_settings ────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS platform_settings (
                id         BIGSERIAL PRIMARY KEY,
                key        VARCHAR(100) NOT NULL,
                value      TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP,
                UNIQUE (key)
            )
        ");

        // ── whatsapp_instances ───────────────────────────────────────────────
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN IF NOT EXISTS name VARCHAR(120) NOT NULL DEFAULT 'Principal'");
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN IF NOT EXISTS webhook_token VARCHAR(64)");
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN IF NOT EXISTS phone_connected BOOLEAN NOT NULL DEFAULT FALSE");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS whatsapp_instances_webhook_token_unique ON whatsapp_instances (webhook_token) WHERE webhook_token IS NOT NULL");
        // Tornar base_url e api_key nullable (as creds passam a ser globais)
        $pdo->exec("ALTER TABLE whatsapp_instances ALTER COLUMN base_url DROP NOT NULL");
        $pdo->exec("ALTER TABLE whatsapp_instances ALTER COLUMN api_key  DROP NOT NULL");

        // Gerar webhook_token para instâncias existentes (via PHP pois gen_random_bytes pode não estar disponível)
        $instances = $pdo->query("SELECT id FROM whatsapp_instances WHERE webhook_token IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $tokStmt = $pdo->prepare("UPDATE whatsapp_instances SET webhook_token = :token WHERE id = :id");
        foreach ($instances as $instanceId) {
            $tokStmt->execute([':token' => bin2hex(random_bytes(32)), ':id' => $instanceId]);
        }

        echo "  ✓ Platform admin schema applied\n";
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $pdo->exec("ALTER TABLE users DROP COLUMN IF EXISTS is_platform_admin");
        $pdo->exec("ALTER TABLE agencies DROP COLUMN IF EXISTS slug");
        $pdo->exec("DROP TABLE IF EXISTS platform_settings");
        $pdo->exec("ALTER TABLE whatsapp_instances DROP COLUMN IF EXISTS name");
        $pdo->exec("ALTER TABLE whatsapp_instances DROP COLUMN IF EXISTS webhook_token");
        $pdo->exec("ALTER TABLE whatsapp_instances DROP COLUMN IF EXISTS phone_connected");
    }

    private function findFkName(\PDO $pdo, string $table, string $column): ?string
    {
        $result = $pdo->query("
            SELECT tc.constraint_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema    = kcu.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = '{$table}'
              AND kcu.column_name = '{$column}'
            LIMIT 1
        ")->fetchColumn();
        return $result ?: null;
    }

    private function generateSlug(\PDO $pdo, string $name, int $id): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $base = trim($base, '-') ?: 'agency';
        $slug = $base;
        $i    = 1;
        while ($pdo->query("SELECT 1 FROM agencies WHERE slug = '{$slug}'")->fetchColumn()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
