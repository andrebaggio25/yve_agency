<?php

declare(strict_types=1);

namespace App\Automations;

/** applies_to=agency: avisa o responsável (in-app) sobre tarefas atrasadas. */
class TaskSlaOverdue extends AbstractAutomation
{
    private const STEPS = [1, 3, 7];

    protected function key(): string { return 'task.sla_overdue'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0;

        $tasks = $this->select("
            SELECT * FROM tasks
            WHERE agency_id = :a AND status != 'done'
              AND due_date IS NOT NULL AND due_date < CURRENT_DATE
        ", [':a' => $agencyId]);

        foreach ($tasks as $task) {
            $days = (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $task['due_date'])) / 86400);
            if (!in_array($days, self::STEPS, true)) continue;

            $acted = $this->once($agencyId, (int) ($task['client_id'] ?: 0) ?: null, "task:{$task['id']}:sla:d{$days}", function () use ($agencyId, $task, $days) {
                $this->notifications->notifyEvent('task.sla_overdue', $agencyId, [
                    'task_id'     => (int) $task['id'],
                    'task_title'  => $task['title'],
                    'assigned_to' => $task['assigned_to'] ? (int) $task['assigned_to'] : null,
                    'days'        => $days,
                ]);
            }, 'inapp');
            if ($acted) $sent++;
        }

        return ['alerted' => $sent];
    }
}
