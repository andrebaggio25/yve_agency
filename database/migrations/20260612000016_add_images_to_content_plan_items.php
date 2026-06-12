<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddImagesToContentPlanItems extends AbstractMigration
{
    public function change(): void
    {
        $this->table('content_plan_items')
            ->addColumn('images', 'jsonb', ['null' => true, 'after' => 'cover_url'])
            ->save();
    }
}
