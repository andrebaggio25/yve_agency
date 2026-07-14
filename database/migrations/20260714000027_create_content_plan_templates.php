<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Modelo semanal por cliente: a estrutura padrão da planificação (dia da
 * semana, hora, plataforma, formato, responsável) que a agência captura de
 * uma semana boa e reaplica nas seguintes. UM modelo por cliente.
 *
 * `items` é jsonb — array de {weekday: 1..7, publish_time, platform,
 * content_type, assigned_to, sort_order}. Só estrutura, nunca conteúdo.
 */
final class CreateContentPlanTemplates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('content_plan_templates', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('agency_id', 'biginteger')
            ->addColumn('client_id', 'biginteger')
            ->addColumn('items', 'jsonb')
            ->addColumn('created_by', 'biginteger', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('agency_id', 'agencies', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['client_id'], ['unique' => true])
            ->addIndex(['agency_id'])
            ->create();
    }
}
