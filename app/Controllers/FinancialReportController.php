<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class FinancialReportController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requirePermission('financial_reports.view');

        $agencyId = Auth::agencyId();
        $year     = (string) ($request->query('year') ?? date('Y'));
        $pdo      = Database::connection();

        // Receita por cliente no ano
        $byClient = $pdo->prepare("
            SELECT cl.name AS client_name,
                   COALESCE(SUM(p.amount), 0)          AS received,
                   COALESCE(SUM(i.total), 0)            AS billed,
                   COUNT(DISTINCT i.id)                 AS invoice_count
            FROM clients cl
            LEFT JOIN invoices i ON i.client_id = cl.id AND i.agency_id = :aid
                AND i.status NOT IN ('cancelled','draft')
                AND EXTRACT(YEAR FROM i.created_at) = :year
            LEFT JOIN payments p ON p.invoice_id = i.id
            WHERE cl.agency_id = :aid2
            GROUP BY cl.id, cl.name
            HAVING COALESCE(SUM(i.total), 0) > 0
            ORDER BY received DESC
        ");
        $byClient->execute([':aid' => $agencyId, ':year' => $year, ':aid2' => $agencyId]);
        $byClient = $byClient->fetchAll(\PDO::FETCH_ASSOC);

        // Faturas vencidas
        $overdue = $pdo->prepare("
            SELECT i.id, i.invoice_number, i.title, i.due_date,
                   i.total, i.amount_paid, (i.total - i.amount_paid) AS remaining,
                   cl.name AS client_name
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            WHERE i.agency_id = :aid AND i.status IN ('overdue','sent')
              AND i.due_date < CURRENT_DATE
            ORDER BY i.due_date ASC
        ");
        $overdue->execute([':aid' => $agencyId]);
        $overdue = $overdue->fetchAll(\PDO::FETCH_ASSOC);

        // Receita mensal do ano
        $monthly = $pdo->prepare("
            SELECT TO_CHAR(payment_date, 'YYYY-MM') AS month,
                   SUM(amount) AS total
            FROM payments
            WHERE agency_id = :aid
              AND EXTRACT(YEAR FROM payment_date) = :year
            GROUP BY month ORDER BY month
        ");
        $monthly->execute([':aid' => $agencyId, ':year' => $year]);
        $monthlyRaw = $monthly->fetchAll(\PDO::FETCH_ASSOC);
        $monthlyMap = array_fill(1, 12, 0);
        foreach ($monthlyRaw as $r) {
            $m = (int) substr($r['month'], 5, 2);
            $monthlyMap[$m] = (float) $r['total'];
        }

        // Top nível
        $totals = $pdo->prepare("
            SELECT
                COALESCE(SUM(p.amount) FILTER (WHERE EXTRACT(YEAR FROM p.payment_date) = :year), 0) AS received_year,
                COALESCE(SUM(i.total - i.amount_paid) FILTER (WHERE i.status IN ('overdue','sent','partial')), 0) AS pending_total,
                COUNT(*) FILTER (WHERE i.status IN ('overdue','sent') AND i.due_date < CURRENT_DATE) AS overdue_count
            FROM invoices i
            JOIN agencies a ON a.id = i.agency_id AND a.id = :aid
            LEFT JOIN payments p ON p.invoice_id = i.id
        ");
        $totals->execute([':aid' => $agencyId, ':year' => $year]);
        $totals = $totals->fetch(\PDO::FETCH_ASSOC) ?: [];

        return $this->view('financeiro.reports', compact('byClient', 'overdue', 'monthlyMap', 'totals', 'year'));
    }
}
