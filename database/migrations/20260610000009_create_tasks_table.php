<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTasksTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS tasks (
                id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                agency_id   BIGINT NOT NULL REFERENCES agencies(id) ON DELETE CASCADE,
                client_id   BIGINT REFERENCES clients(id) ON DELETE SET NULL,
                assigned_to BIGINT REFERENCES users(id) ON DELETE SET NULL,
                created_by  BIGINT NOT NULL REFERENCES users(id) ON DELETE NO ACTION,
                title       VARCHAR(255) NOT NULL,
                description TEXT,
                status      VARCHAR(30)  NOT NULL DEFAULT 'todo',
                priority    VARCHAR(20)  NOT NULL DEFAULT 'medium',
                due_date    DATE,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        $this->execute("CREATE INDEX IF NOT EXISTS tasks_agency_status_idx ON tasks(agency_id, status)");
        $this->execute("CREATE INDEX IF NOT EXISTS tasks_agency_client_idx ON tasks(agency_id, client_id)");
        $this->execute("CREATE INDEX IF NOT EXISTS tasks_assigned_idx      ON tasks(assigned_to)");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS tasks");
    }
}
