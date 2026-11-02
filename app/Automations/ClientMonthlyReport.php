<?php

declare(strict_types=1);

namespace App\Automations;

/** Mensal: envia ao cliente (opt-in) o link do painel dele com os números do mês anterior. */
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

            // A mensagem é só o link. Antes ela levava /executive-report, rota
            // interna da agência: o cliente caía no login. Portal desligado (sem
            // token ou desabilitado) não tem link que abra — melhor não mandar.
            //
            // Não mandar é a decisão certa; sumir calado não é: sem a linha na
            // timeline ninguém responde por que a cliente não recebeu o
            // relatório (e cliente cadastrada normalmente nasce sem token).
            $reportUrl = empty($client['portal_enabled'])
                ? null
                : \App\Services\ClientService::portalUrl($client['portal_token'] ?? null);
            if ($reportUrl === null) {
                $this->notifications->skipClientDelivery($agencyId, $client, 'client_monthly_report', 'portal_off', [
                    'client_name' => $client['name'] ?? '',
                    'month_label' => $monthLbl,
                ]);
                $skipped++;
                continue;
            }

            $acted = $this->once($agencyId, $clientId, "client:{$clientId}:report:{$period}", function () use ($agencyId, $client, $monthLbl, $reportUrl) {
                $this->notifications->notifyEvent('report.client_monthly', $agencyId, [
                    'client'      => $client,
                    'report_url'  => $reportUrl,
                    'month_label' => $monthLbl,
                ]);
            }, 'whatsapp');

            $acted ? $sent++ : $skipped++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
