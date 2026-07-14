<?php

declare(strict_types=1);

namespace App\Automations;

class ApprovalReminder extends AbstractAutomation
{
    /** Dias desde o envio em que cobramos a aprovação. */
    private const STEPS = [2, 4, 7];

    protected function key(): string { return 'content.approval_reminder'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0; $skipped = 0;

        $plans = $this->select("
            SELECT cp.*, c.portal_token AS client_portal_token
            FROM content_plans cp
            JOIN clients c ON c.id = cp.client_id
            WHERE cp.agency_id = :a AND cp.status = 'sent' AND cp.sent_at IS NOT NULL
              AND cp.sent_at <= NOW() - INTERVAL '2 days'
        ", [':a' => $agencyId]);

        foreach ($plans as $plan) {
            $days = (int) floor((time() - strtotime((string) $plan['sent_at'])) / 86400);
            if (!in_array($days, self::STEPS, true)) continue;

            $clientId = (int) $plan['client_id'];
            if (!$this->gate($agencyId, $clientId)) { $skipped++; continue; }

            $acted = $this->once($agencyId, $clientId, "plan:{$plan['id']}:reminder:d{$days}", function () use ($agencyId, $plan, $clientId, $days) {
                $this->notifications->notifyEvent('content.approval_reminder', $agencyId, [
                    'plan_id'      => (int) $plan['id'],
                    'plan_title'   => $plan['title'],
                    'client'       => $this->client($agencyId, $clientId),
                    'approval_url' => \App\Services\ContentPlanService::portalPlanUrl($plan['client_portal_token'] ?? null, (int) $plan['id']),
                    'days'         => $days,
                ]);
            }, 'whatsapp');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
