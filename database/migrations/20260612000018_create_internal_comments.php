<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInternalComments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('internal_comments')
            ->addColumn('agency_id',   'integer',   ['null' => false])
            ->addColumn('entity_type', 'string',    ['limit' => 50,  'null' => false])
            ->addColumn('entity_id',   'biginteger', ['null' => false, 'signed' => false])
            ->addColumn('user_id',     'biginteger', ['null' => false, 'signed' => false])
            ->addColumn('message',     'text',       ['null' => false])
            ->addColumn('created_at',  'timestamp',  ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['entity_type', 'entity_id', 'created_at'])
            ->save();
    }
}
