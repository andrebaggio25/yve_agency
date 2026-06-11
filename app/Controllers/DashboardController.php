<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\InvoiceRepository;

class DashboardController extends Controller
{
    public function __construct(private InvoiceRepository $invoiceRepo) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();
        $pdo      = Database::connection();

        $active_clients = (int) $pdo->query(
            "SELECT COUNT(*) FROM clients WHERE agency_id = {$agencyId} AND status = 'active'"
        )->fetchColumn();

        $pending_plans = (int) $pdo->query(
            "SELECT COUNT(*) FROM content_plans WHERE agency_id = {$agencyId} AND status IN ('draft','revision')"
        )->fetchColumn();

        $pending_approvals = (int) $pdo->query(
            "SELECT COUNT(*) FROM content_plans WHERE agency_id = {$agencyId} AND status = 'sent'"
        )->fetchColumn();

        $recent_plans = $pdo->query(
            "SELECT cp.id, cp.title, cp.status, cp.week_start, c.name AS client_name
             FROM content_plans cp
             JOIN clients c ON c.id = cp.client_id
             WHERE cp.agency_id = {$agencyId}
             ORDER BY cp.created_at DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $financialSummary = Auth::canAny('invoices.view', 'contracts.view')
            ? $this->invoiceRepo->summaryByAgency($agencyId)
            : null;

        return $this->view('dashboard.index', [
            'stats' => [
                'active_clients'    => $active_clients,
                'pending_plans'     => $pending_plans,
                'pending_approvals' => $pending_approvals,
            ],
            'recent_plans'     => $recent_plans,
            'financialSummary' => $financialSummary,
            'user'             => Auth::user(),
        ]);
    }
}
