<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\DashboardRepository;
use App\Repositories\InvoiceRepository;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardRepository $dashboardRepo,
        private InvoiceRepository $invoiceRepo,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();

        $financialSummary = Auth::canAny('invoices.view', 'contracts.view')
            ? $this->invoiceRepo->summaryByAgency($agencyId)
            : null;

        // "Meu dia" (PROD-08): cada bloco respeita a permissão do módulo de origem.
        $myDay = [
            'stalled_approvals' => Auth::can('content.view')
                ? $this->dashboardRepo->stalledApprovals($agencyId) : [],
            'invoices_due'      => Auth::can('invoices.view')
                ? $this->dashboardRepo->invoicesNeedingAttention($agencyId) : [],
            'overdue_tasks'     => Auth::can('tasks.view')
                ? $this->dashboardRepo->overdueTasks($agencyId) : [],
            'broken_syncs'      => Auth::can('ads_metrics.view')
                ? $this->dashboardRepo->brokenSyncs($agencyId) : [],
        ];

        return $this->view('dashboard.index', [
            'stats'            => $this->dashboardRepo->statsByAgency($agencyId),
            'recent_plans'     => $this->dashboardRepo->recentPlans($agencyId),
            'financialSummary' => $financialSummary,
            'myDay'            => $myDay,
            'user'             => Auth::user(),
        ]);
    }
}
