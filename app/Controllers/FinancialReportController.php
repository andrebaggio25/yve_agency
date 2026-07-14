<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinancialReportRepository;
use App\Support\Auth;

class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportRepository $reports) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('financial_reports.view');

        $agencyId = (int) Auth::agencyId();
        $year     = (string) ($request->query('year') ?? date('Y'));

        $byClient   = $this->reports->revenueByClient($agencyId, $year);
        $overdue    = $this->reports->overdueInvoices($agencyId);
        $monthlyMap = $this->reports->monthlyRevenue($agencyId, $year);
        $totals     = $this->reports->yearTotals($agencyId, $year);

        return $this->view('financeiro.reports', compact('byClient', 'overdue', 'monthlyMap', 'totals', 'year'));
    }
}
