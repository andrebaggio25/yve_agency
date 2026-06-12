<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLogoUrlToClients extends AbstractMigration
{
    public function change(): void
    {
        $this->table('clients')
            ->addColumn('logo_url', 'string', ['limit' => 512, 'null' => true, 'after' => 'name'])
            ->save();
    }
}
