<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\AgencyRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ExecutiveReportRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\TaskRepository;
use App\Repositories\AdMetricsRepository;

class ReportController extends Controller
{
    public function __construct(
        private ClientRepository          $clientRepo,
        private InvoiceRepository         $invoiceRepo,
        private TaskRepository            $taskRepo,
        private AdMetricsRepository       $adMetrics,
        private ExecutiveReportRepository $reports,
        private AgencyRepository          $agencies,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();
        [$since, $until] = $this->period($request);

        return $this->view('executive.index', [
            'clients'       => $this->clientRepo->findByAgency($agencyId),
            'since'         => $since,
            'until'         => $until,
            'financialKpis' => $this->invoiceRepo->summaryByAgency($agencyId),
            'revenueTrend'  => $this->reports->revenueTrend($agencyId),
            'contentKpis'   => $this->reports->contentKpis($agencyId),
            'taskKpis'      => $this->taskRepo->countByStatus($agencyId),
            'adsKpis'       => $this->reports->adsKpis($agencyId, $since, $until),
            'organicKpis'   => $this->reports->organicKpis($agencyId, $since, $until),
            'clientSummary' => $this->reports->clientSummary($agencyId),
            'topCampaigns'  => array_slice($this->adMetrics->metricsPerCampaignForAgency($agencyId, $since, $until), 0, 10),
        ]);
    }

    /** Printable per-client PDF report (browser print-to-PDF). */
    public function clientReport(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();
        $clientId = (int) $request->param('clientId');

        $client = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client) {
            $this->withError('Cliente não encontrado.');
            return $this->redirect('/relatorio-executivo');
        }

        [$since, $until] = $this->period($request);

        return $this->view('executive.client_report', [
            'client'         => $client,
            'agency'         => $this->agencies->findBasic($agencyId),
            'since'          => $since,
            'until'          => $until,
            'invoices'       => $this->invoiceRepo->listByAgency($agencyId, ['client_id' => $clientId]),
            'invoiceSummary' => $this->reports->clientInvoiceSummary($clientId, $agencyId),
            'plans'          => $this->reports->clientPlans($clientId, $agencyId),
            'tasks'          => $this->reports->clientTasks($clientId, $agencyId),
            'adMetrics'      => $this->reports->clientAdMetrics($clientId, $agencyId, $since, $until),
            'organicMetrics' => $this->reports->clientOrganicMetrics($clientId, $agencyId, $since, $until),
        ]);
    }

    /**
     * Período do filtro, validado. Data fora do formato ISO cai no padrão
     * (últimos 30 dias) — o valor vai para SQL como parâmetro, mas validar
     * aqui evita período absurdo e mantém a view previsível.
     *
     * @return array{0:string,1:string}
     */
    private function period(Request $request): array
    {
        $rawSince = (string) $request->input('since', '');
        $rawUntil = (string) $request->input('until', '');

        return [
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawSince) ? $rawSince : date('Y-m-d', strtotime('-30 days')),
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawUntil) ? $rawUntil : date('Y-m-d'),
        ];
    }
}
