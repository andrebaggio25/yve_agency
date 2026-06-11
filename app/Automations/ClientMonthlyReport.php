<?php

declare(strict_types=1);

namespace App\Automations;

/** Mensal: envia ao cliente (opt-in) o link do relatório executivo do mês anterior. */
class ClientMonthlyReport extends AbstractAutomation
{
    protected function key(): string { return 'report.client_monthly'; }

    public function run(int $agencyId, array $rule): array
    {
        $sent = 0; $skipped = 0;
        $period   = date('Y-m', strtotime('first day of last month'));
        $monthLbl = date('m/Y', strtotime('first day of last month'));

        $clients = $this->select(
            "SELECT * FROM clients WHERE agency_id = :a AND status = 'active'",
            [':a' => $agencyId],
        );

        foreach ($clients as $client) {
            $clientId = (int) $client['id'];
            if (!$this->gate($agencyId, $clientId)) { $skipped++; continue; }

            $acted = $this->once($agencyId, $clientId, "client:{$clientId}:report:{$period}", function () use ($agencyId, $client, $monthLbl) {
                $this->notifications->notifyEvent('report.client_monthly', $agencyId, [
                    'client'      => $client,
                    'report_url'  => rtrim((string) env('APP_URL', ''), '/') . "/executive-report?client={$client['id']}",
                    'month_label' => $monthLbl,
                ]);
            }, 'email');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
