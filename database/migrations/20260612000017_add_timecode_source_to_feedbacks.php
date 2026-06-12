<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTimecodeSourceToFeedbacks extends AbstractMigration
{
    public function change(): void
    {
        $this->table('content_feedbacks')
            ->addColumn('timecode_seconds', 'integer', ['null' => true])
            ->addColumn('source', 'string', ['limit' => 20, 'default' => 'client', 'null' => false])
            ->save();
    }
}
