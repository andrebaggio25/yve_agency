<?php

declare(strict_types=1);

namespace App\Automations;

class InvoiceOverdueReminder extends AbstractAutomation
{
    /** Dias de atraso em que reenviamos a cobrança. */
    private const STEPS = [1, 7, 14, 30];

    protected function key(): string { return 'billing.invoice_overdue'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0; $skipped = 0;

        $invoices = $this->select("
            SELECT * FROM invoices
            WHERE agency_id = :a
              AND status IN ('sent','partial','overdue')
              AND due_date < CURRENT_DATE
        ", [':a' => $agencyId]);

        foreach ($invoices as $inv) {
            $days = (int) floor((strtotime(date('Y-m-d')) - strtotime((string) $inv['due_date'])) / 86400);
            if (!in_array($days, self::STEPS, true)) continue;

            $clientId = (int) $inv['client_id'];
            if (!$this->gate($agencyId, $clientId)) { $skipped++; continue; }

            $acted = $this->once($agencyId, $clientId, "invoice:{$inv['id']}:overdue:d{$days}", function () use ($agencyId, $inv, $clientId, $days) {
                $this->notifications->notifyEvent('billing.invoice_overdue', $agencyId, [
                    'invoice'      => $inv,
                    'client'       => $this->client($agencyId, $clientId),
                    'days_overdue' => $days,
                ]);
            }, 'whatsapp');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
