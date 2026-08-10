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
use App\Services\PdfService;

class ReportController extends Controller
{
    public function __construct(
        private ClientRepository          $clientRepo,
        private InvoiceRepository         $invoiceRepo,
        private TaskRepository            $taskRepo,
        private AdMetricsRepository       $adMetrics,
        private ExecutiveReportRepository $reports,
        private AgencyRepository          $agencies,
        private PdfService                $pdf,
    ) {}

    public function index(Request $request): Response
    {
        $this->requireAgencyOverview();

        $agencyId = (int) Auth::agencyId();
        [$since, $until] = $this->period($request);
        $canFin = self::canSeeMoney();

        return $this->view('executive.index', [
            'clients'       => $this->clientRepo->findByAgency($agencyId),
            'since'         => $since,
            'until'         => $until,
            'canFin'        => $canFin,
            'financialKpis' => $canFin ? $this->invoiceRepo->summaryByAgency($agencyId) : null,
            'revenueTrend'  => $canFin ? $this->reports->revenueTrend($agencyId) : [],
            'contentKpis'   => $this->reports->contentKpis($agencyId),
            'taskKpis'      => $this->taskRepo->countByStatus($agencyId),
            'adsKpis'       => $this->reports->adsKpis($agencyId, $since, $until),
            'organicKpis'   => $this->reports->organicKpis($agencyId, $since, $until),
            'clientSummary' => $this->reports->clientSummary($agencyId),
            'topCampaigns'  => array_slice($this->adMetrics->metricsPerCampaignForAgency($agencyId, $since, $until), 0, 10),
        ]);
    }

    /** PDF real do relatório do cliente (UX-04) — o que se manda para a cliente. */
    public function clientReportPdf(Request $request): Response
    {
        $response = $this->clientReport($request);

        // Se o clientReport redirecionou (cliente inexistente), respeita.
        if ($response->getStatus() !== 200) {
            return $response;
        }

        $client = $this->clientRepo->findByIdAndAgency(
            (int) $request->param('clientId'),
            (int) Auth::agencyId()
        );

        return Response::file(
            $this->pdf->fromHtml($response->getBody()),
            $this->pdf->filename('relatorio', (string) ($client['name'] ?? ''), date('Y-m'))
        );
    }

    /** Printable per-client report (base do PDF e da visualização). */
    public function clientReport(Request $request): Response
    {
        $this->requireAgencyOverview();

        $agencyId = (int) Auth::agencyId();
        $clientId = (int) $request->param('clientId');

        // Defesa em profundidade: a rota já passa pelo ClientAccessMiddleware,
        // mas o relatório carrega financeiro do cliente — não depende de rota.
        Auth::requireClientAccess($clientId);

        $client = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client) {
            $this->withError('Cliente não encontrado.');
            return $this->redirect('/relatorio-executivo');
        }

        [$since, $until] = $this->period($request);
        $canFin = self::canSeeMoney();
        // Investimento em mídia também é dinheiro: mesmo gate do módulo de
        // tráfego, senão o relatório vira um bypass de `ads_metrics.view`.
        $canAds = Auth::can('ads_metrics.view');

        return $this->view('executive.client_report', [
            'client'         => $client,
            'agency'         => $this->agencies->findBasic($agencyId),
            'since'          => $since,
            'until'          => $until,
            'canFin'         => $canFin,
            'canAds'         => $canAds,
            'invoices'       => $canFin ? $this->invoiceRepo->listByAgency($agencyId, ['client_id' => $clientId]) : [],
            'invoiceSummary' => $canFin ? $this->reports->clientInvoiceSummary($clientId, $agencyId) : null,
            'plans'          => $this->reports->clientPlans($clientId, $agencyId),
            'tasks'          => $this->reports->clientTasks($clientId, $agencyId),
            'adMetrics'      => $canAds ? $this->reports->clientAdMetrics($clientId, $agencyId, $since, $until) : null,
            'organicMetrics' => $this->reports->clientOrganicMetrics($clientId, $agencyId, $since, $until),
        ]);
    }

    /**
     * Porta de entrada do relatório: é uma visão da **agência**, então exige
     * alguma permissão de ver cliente. `dashboard.view` sozinho não serve —
     * os papéis de cliente (client_admin & cia.) o têm e entrariam na visão
     * consolidada de todos os outros clientes da agência.
     */
    private function requireAgencyOverview(): void
    {
        Auth::requireAnyPermission('clients.view', 'clients.view_all', 'clients.view_basic');
    }

    /** Quem pode ver dinheiro. Mesmo critério do Dashboard (+ relatórios). */
    private static function canSeeMoney(): bool
    {
        return Auth::canAny('invoices.view', 'contracts.view', 'financial_reports.view');
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
