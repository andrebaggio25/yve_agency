<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * SCHEMA-01: adiciona as foreign keys que faltavam nas tabelas de Drive/ClickUp.
 * Sem elas, excluir um cliente/agência deixava pastas, arquivos e integrações
 * órfãos. Limpa órfãos existentes antes de criar cada FK (senão a criação falha).
 *
 * Idempotente: cada FK só é criada se ainda não existir (checagem em pg_constraint).
 */
final class AddMissingFksDriveClickup extends AbstractMigration
{
    public function up(): void
    {
        // 1) Limpa órfãos (ordem: filhos antes dos pais).
        $this->execute("DELETE FROM drive_files   WHERE agency_id NOT IN (SELECT id FROM agencies)");
        $this->execute("DELETE FROM drive_files   WHERE client_id NOT IN (SELECT id FROM clients)");
        $this->execute("DELETE FROM drive_folders WHERE agency_id NOT IN (SELECT id FROM agencies)");
        $this->execute("DELETE FROM drive_folders WHERE client_id NOT IN (SELECT id FROM clients)");
        $this->execute("DELETE FROM google_drive_integrations WHERE agency_id NOT IN (SELECT id FROM agencies)");
        $this->execute("DELETE FROM clickup_integrations      WHERE agency_id NOT IN (SELECT id FROM agencies)");

        // 2) Cria as FKs (guardadas por pg_constraint para permitir re-execução segura).
        $this->addFk('drive_folders', 'drive_folders_agency_fk', 'agency_id', 'agencies');
        $this->addFk('drive_folders', 'drive_folders_client_fk', 'client_id', 'clients');
        $this->addFk('drive_files',   'drive_files_agency_fk',   'agency_id', 'agencies');
        $this->addFk('drive_files',   'drive_files_client_fk',   'client_id', 'clients');
        $this->addFk('google_drive_integrations', 'gdrive_integrations_agency_fk', 'agency_id', 'agencies');
        $this->addFk('clickup_integrations',      'clickup_integrations_agency_fk', 'agency_id', 'agencies');
    }

    public function down(): void
    {
        foreach ([
            ['drive_folders', 'drive_folders_agency_fk'],
            ['drive_folders', 'drive_folders_client_fk'],
            ['drive_files',   'drive_files_agency_fk'],
            ['drive_files',   'drive_files_client_fk'],
            ['google_drive_integrations', 'gdrive_integrations_agency_fk'],
            ['clickup_integrations',      'clickup_integrations_agency_fk'],
        ] as [$table, $name]) {
            $this->execute("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }

    private function addFk(string $table, string $name, string $column, string $refTable): void
    {
        $this->execute("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$name}') THEN
                    ALTER TABLE {$table}
                        ADD CONSTRAINT {$name}
                        FOREIGN KEY ({$column}) REFERENCES {$refTable} (id) ON DELETE CASCADE;
                END IF;
            END \$\$;
        ");
    }
}
