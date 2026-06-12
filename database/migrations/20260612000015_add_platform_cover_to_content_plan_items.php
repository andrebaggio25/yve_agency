<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPlatformCoverToContentPlanItems extends AbstractMigration
{
    public function change(): void
    {
        $this->table('content_plan_items')
            ->addColumn('platform',  'string', ['limit' => 50, 'null' => true, 'after' => 'content_type'])
            ->addColumn('cover_url', 'text',   ['null' => true, 'after' => 'drive_url'])
            ->save();
    }
}
