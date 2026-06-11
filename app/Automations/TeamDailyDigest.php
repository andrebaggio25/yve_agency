<?php

declare(strict_types=1);

namespace App\Automations;

/** applies_to=agency: resumo diário (in-app) para a equipe. */
class TeamDailyDigest extends AbstractAutomation
{
    protected function key(): string { return 'digest.team_daily'; }

    public function run(int $agencyId, array $rule): array
    {
        $today = date('Y-m-d');

        $acted = $this->once($agencyId, null, "digest:{$agencyId}:{$today}", function () use ($agencyId) {
            $row = $this->select("
                SELECT
                    (SELECT COUNT(*) FROM tasks         WHERE agency_id = :a1 AND status != 'done' AND due_date = CURRENT_DATE) AS tasks_today,
                    (SELECT COUNT(*) FROM content_plans WHERE agency_id = :a2 AND status = 'sent')                               AS plans_pending,
                    (SELECT COUNT(*) FROM invoices       WHERE agency_id = :a3 AND status IN ('sent','partial','overdue'))       AS invoices_open
            ", [':a1' => $agencyId, ':a2' => $agencyId, ':a3' => $agencyId])[0] ?? [];

            $this->notifications->notifyEvent('digest.team_daily', $agencyId, [
                'tasks_today'   => (int) ($row['tasks_today'] ?? 0),
                'plans_pending' => (int) ($row['plans_pending'] ?? 0),
                'invoices_open' => (int) ($row['invoices_open'] ?? 0),
            ]);
        }, 'inapp');

        return ['digest' => $acted ? 1 : 0];
    }
}
