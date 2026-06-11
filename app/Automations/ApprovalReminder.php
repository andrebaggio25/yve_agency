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
            SELECT * FROM content_plans
            WHERE agency_id = :a AND status = 'sent' AND sent_at IS NOT NULL
              AND sent_at <= NOW() - INTERVAL '2 days'
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
                    'approval_url' => rtrim((string) env('APP_URL', ''), '/') . "/aprovacoes/{$plan['id']}",
                    'days'         => $days,
                ]);
            }, 'whatsapp');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
