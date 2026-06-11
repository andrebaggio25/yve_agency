<?php

declare(strict_types=1);

namespace App\Automations;

/** applies_to=agency: avisa a equipe (in-app) sobre contratos prestes a vencer. */
class ContractExpiring extends AbstractAutomation
{
    private const STEPS = [30, 15, 7, 1];

    protected function key(): string { return 'contract.expiring'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0;

        $contracts = $this->select("
            SELECT ct.*, c.name AS client_name
            FROM contracts ct
            LEFT JOIN clients c ON c.id = ct.client_id
            WHERE ct.agency_id = :a AND ct.status = 'active'
              AND ct.end_date IS NOT NULL
              AND ct.end_date BETWEEN CURRENT_DATE AND CURRENT_DATE + 30
        ", [':a' => $agencyId]);

        foreach ($contracts as $ct) {
            $days = (int) floor((strtotime((string) $ct['end_date']) - strtotime(date('Y-m-d'))) / 86400);
            if (!in_array($days, self::STEPS, true)) continue;

            $acted = $this->once($agencyId, (int) $ct['client_id'], "contract:{$ct['id']}:expiring:d{$days}", function () use ($agencyId, $ct, $days) {
                $this->notifications->notifyEvent('contract.expiring', $agencyId, [
                    'contract_id'    => (int) $ct['id'],
                    'contract_title' => $ct['title'],
                    'client_name'    => $ct['client_name'] ?? '',
                    'end_date'       => $ct['end_date'],
                    'days'           => $days,
                ]);
            }, 'inapp');
            if ($acted) $sent++;
        }

        return ['alerted' => $sent];
    }
}
