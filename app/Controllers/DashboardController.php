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

        return $this->view('dashboard.index', [
            'stats'            => $this->dashboardRepo->statsByAgency($agencyId),
            'recent_plans'     => $this->dashboardRepo->recentPlans($agencyId),
            'financialSummary' => $financialSummary,
            'user'             => Auth::user(),
        ]);
    }
}
