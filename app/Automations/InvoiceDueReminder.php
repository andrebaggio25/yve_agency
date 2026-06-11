<?php

declare(strict_types=1);

namespace App\Automations;

class InvoiceDueReminder extends AbstractAutomation
{
    protected function key(): string { return 'billing.invoice_due_reminder'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0; $skipped = 0;

        $invoices = $this->select("
            SELECT * FROM invoices
            WHERE agency_id = :a
              AND status IN ('sent','partial')
              AND due_date = CURRENT_DATE + 3
        ", [':a' => $agencyId]);

        foreach ($invoices as $inv) {
            $clientId = (int) $inv['client_id'];
            if (!$this->gate($agencyId, $clientId)) { $skipped++; continue; }

            $acted = $this->once($agencyId, $clientId, "invoice:{$inv['id']}:due3", function () use ($agencyId, $inv, $clientId) {
                $this->notifications->notifyEvent('billing.invoice_due_reminder', $agencyId, [
                    'invoice' => $inv,
                    'client'  => $this->client($agencyId, $clientId),
                    'days'    => 3,
                ]);
            }, 'whatsapp');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
