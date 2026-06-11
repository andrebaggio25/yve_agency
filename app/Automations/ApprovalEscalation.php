<?php

declare(strict_types=1);

namespace App\Automations;

/** applies_to=agency: avisa a equipe (in-app) sobre planos parados há 5+ dias. */
class ApprovalEscalation extends AbstractAutomation
{
    protected function key(): string { return 'content.approval_escalation'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0;

        $plans = $this->select("
            SELECT cp.*, c.name AS client_name
            FROM content_plans cp
            LEFT JOIN clients c ON c.id = cp.client_id
            WHERE cp.agency_id = :a AND cp.status = 'sent' AND cp.sent_at IS NOT NULL
              AND cp.sent_at <= NOW() - INTERVAL '5 days'
        ", [':a' => $agencyId]);

        foreach ($plans as $plan) {
            $days = (int) floor((time() - strtotime((string) $plan['sent_at'])) / 86400);
            $acted = $this->once($agencyId, (int) $plan['client_id'], "plan:{$plan['id']}:escalation", function () use ($agencyId, $plan, $days) {
                $this->notifications->notifyEvent('content.approval_escalation', $agencyId, [
                    'plan_id'     => (int) $plan['id'],
                    'plan_title'  => $plan['title'],
                    'client_name' => $plan['client_name'] ?? '',
                    'days'        => $days,
                ]);
            }, 'inapp');
            if ($acted) $sent++;
        }

        return ['escalated' => $sent];
    }
}
