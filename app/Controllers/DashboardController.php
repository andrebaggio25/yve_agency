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

        // One round-trip instead of four separate queries
        $statsStmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM clients      WHERE agency_id = :a1 AND status = 'active')           AS active_clients,
                (SELECT COUNT(*) FROM content_plans WHERE agency_id = :a2 AND status IN ('draft','revision')) AS pending_plans,
                (SELECT COUNT(*) FROM content_plans WHERE agency_id = :a3 AND status = 'sent')             AS pending_approvals
        ");
        $statsStmt->execute([':a1' => $agencyId, ':a2' => $agencyId, ':a3' => $agencyId]);
        $statsRow = $statsStmt->fetch();

        $recentStmt = $pdo->prepare("
            SELECT cp.id, cp.title, cp.status, cp.week_start, c.name AS client_name
            FROM content_plans cp
            JOIN clients c ON c.id = cp.client_id
            WHERE cp.agency_id = :aid
            ORDER BY cp.created_at DESC LIMIT 5
        ");
        $recentStmt->execute([':aid' => $agencyId]);
        $recent_plans = $recentStmt->fetchAll();

        $financialSummary = Auth::canAny('invoices.view', 'contracts.view')
            ? $this->invoiceRepo->summaryByAgency($agencyId)
            : null;

        return $this->view('dashboard.index', [
            'stats' => [
                'active_clients'    => (int) $statsRow['active_clients'],
                'pending_plans'     => (int) $statsRow['pending_plans'],
                'pending_approvals' => (int) $statsRow['pending_approvals'],
            ],
            'recent_plans'     => $recent_plans,
            'financialSummary' => $financialSummary,
            'user'             => Auth::user(),
        ]);
    }
}
