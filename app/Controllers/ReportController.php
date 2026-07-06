<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\TaskRepository;
use App\Repositories\AdMetricsRepository;

class ReportController extends Controller
{
    public function __construct(
        private ClientRepository        $clientRepo,
        private InvoiceRepository       $invoiceRepo,
        private TaskRepository          $taskRepo,
        private AdMetricsRepository     $adMetrics,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();
        $pdo      = Database::connection();

        $rawSince = (string) $request->input('since', '');
        $rawUntil = (string) $request->input('until', '');
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawSince) ? $rawSince : date('Y-m-d', strtotime('-30 days'));
        $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawUntil) ? $rawUntil : date('Y-m-d');

        // ── Clients ───────────────────────────────────────────────────────────
        $clients = $this->clientRepo->findByAgency($agencyId);

        // ── Financial KPIs ────────────────────────────────────────────────────
        $financialKpis = $this->invoiceRepo->summaryByAgency($agencyId);

        // Monthly revenue trend (last 12 months)
        $revenueTrend = $pdo->prepare("
            SELECT TO_CHAR(DATE_TRUNC('month', COALESCE(paid_at, due_date)), 'YYYY-MM') AS month,
                   COALESCE(SUM(amount_paid), 0) AS received,
                   COALESCE(SUM(total), 0)        AS billed
            FROM invoices
            WHERE agency_id = :aid
              AND status != 'cancelled'
              AND DATE_TRUNC('month', COALESCE(paid_at, due_date)) >= DATE_TRUNC('month', NOW() - INTERVAL '11 months')
            GROUP BY 1 ORDER BY 1
        ");
        $revenueTrend->execute([':aid' => $agencyId]);
        $revenueTrend = $revenueTrend->fetchAll(\PDO::FETCH_ASSOC);

        // ── Content KPIs ──────────────────────────────────────────────────────
        $contentKpis = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'approved')  AS approved,
                COUNT(*) FILTER (WHERE status = 'sent')      AS awaiting,
                COUNT(*) FILTER (WHERE status IN ('draft','revision')) AS draft,
                COUNT(*) FILTER (WHERE status = 'revision')  AS revision
            FROM content_plans WHERE agency_id = :aid
        ");
        $contentKpis->execute([':aid' => $agencyId]);
        $contentKpis = $contentKpis->fetch(\PDO::FETCH_ASSOC);

        // ── Tasks KPIs ────────────────────────────────────────────────────────
        $taskKpis = $this->taskRepo->countByStatus($agencyId);

        // ── Ads KPIs (agency-wide, period) ────────────────────────────────────
        $adsKpis = $pdo->prepare("
            SELECT
                COALESCE(SUM(m.spend), 0)           AS total_spend,
                COALESCE(SUM(m.impressions), 0)      AS total_impressions,
                COALESCE(SUM(m.clicks), 0)           AS total_clicks,
                COALESCE(SUM(m.conversions), 0)      AS total_conversions,
                CASE WHEN SUM(m.spend) > 0
                     THEN SUM(m.conversion_value) / SUM(m.spend) ELSE 0 END AS avg_roas
            FROM ad_daily_metrics m
            JOIN ad_accounts a ON a.id = m.ad_account_id AND a.agency_id = :aid
            WHERE m.entity_type = 'campaign'
              AND m.date BETWEEN :since AND :until
        ");
        $adsKpis->execute([':aid' => $agencyId, ':since' => $since, ':until' => $until]);
        $adsKpis = $adsKpis->fetch(\PDO::FETCH_ASSOC);

        // ── Organic KPIs (agency-wide, period) ───────────────────────────────
        $organicKpis = $pdo->prepare("
            SELECT
                COALESCE(SUM(p.reach), 0)       AS total_reach,
                COALESCE(SUM(p.impressions), 0)  AS total_impressions,
                COALESCE(SUM(p.likes + p.comments + p.shares + p.saves), 0) AS total_engagement,
                COUNT(*)                          AS total_posts
            FROM organic_posts p
            JOIN organic_accounts oa ON oa.id = p.organic_account_id AND oa.agency_id = :aid
            WHERE p.posted_at::date BETWEEN :since AND :until
        ");
        $organicKpis->execute([':aid' => $agencyId, ':since' => $since, ':until' => $until]);
        $organicKpis = $organicKpis->fetch(\PDO::FETCH_ASSOC);

        // ── Per-client summary ────────────────────────────────────────────────
        $clientSummary = $pdo->prepare("
            SELECT
                c.id, c.name, c.status,
                COUNT(DISTINCT i.id)  FILTER (WHERE i.status NOT IN ('cancelled','draft')) AS invoices_count,
                COALESCE(SUM(i.total) FILTER (WHERE i.status NOT IN ('cancelled','draft')), 0) AS invoiced,
                COALESCE(SUM(i.amount_paid) FILTER (WHERE i.status NOT IN ('cancelled')), 0) AS paid,
                COALESCE(SUM(i.total - i.amount_paid) FILTER (WHERE i.status IN ('sent','overdue','partial')), 0) AS pending,
                COUNT(DISTINCT cp.id) FILTER (WHERE cp.status = 'sent') AS plans_awaiting,
                COUNT(DISTINCT cp.id) FILTER (WHERE cp.status = 'approved') AS plans_approved,
                COUNT(DISTINCT t.id)  FILTER (WHERE t.status != 'done') AS open_tasks
            FROM clients c
            LEFT JOIN invoices i ON i.client_id = c.id AND i.agency_id = c.agency_id
            LEFT JOIN content_plans cp ON cp.client_id = c.id AND cp.agency_id = c.agency_id
            LEFT JOIN tasks t ON t.client_id = c.id AND t.agency_id = c.agency_id
            WHERE c.agency_id = :aid AND c.status = 'active'
            GROUP BY c.id, c.name, c.status
            ORDER BY invoiced DESC, c.name
        ");
        $clientSummary->execute([':aid' => $agencyId]);
        $clientSummary = $clientSummary->fetchAll(\PDO::FETCH_ASSOC);

        // Top campaigns in period
        $topCampaigns = $this->adMetrics->metricsPerCampaignForAgency($agencyId, $since, $until);
        $topCampaigns = array_slice($topCampaigns, 0, 10);

        return $this->view('executive.index', [
            'clients'       => $clients,
            'since'         => $since,
            'until'         => $until,
            'financialKpis' => $financialKpis,
            'revenueTrend'  => $revenueTrend,
            'contentKpis'   => $contentKpis,
            'taskKpis'      => $taskKpis,
            'adsKpis'       => $adsKpis,
            'organicKpis'   => $organicKpis,
            'clientSummary' => $clientSummary,
            'topCampaigns'  => $topCampaigns,
        ]);
    }

    /** Printable per-client PDF report (browser print-to-PDF). */
    public function clientReport(Request $request): Response
    {
        Auth::requirePermission('dashboard.view');

        $agencyId = (int) Auth::agencyId();
        $clientId = (int) $request->param('clientId');
        $pdo      = Database::connection();

        $client = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client) {
            $this->withError('Cliente não encontrado.');
            return $this->redirect('/relatorio-executivo');
        }

        $rawSince2 = (string) $request->input('since', '');
        $rawUntil2 = (string) $request->input('until', '');
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawSince2) ? $rawSince2 : date('Y-m-d', strtotime('-30 days'));
        $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawUntil2) ? $rawUntil2 : date('Y-m-d');

        // Invoices
        $invoices = $this->invoiceRepo->listByAgency($agencyId, ['client_id' => $clientId]);
        $invoiceSummary = $pdo->prepare("
            SELECT
                COALESCE(SUM(total), 0)        AS total_billed,
                COALESCE(SUM(amount_paid), 0)  AS total_paid,
                COALESCE(SUM(total - amount_paid) FILTER (WHERE status IN ('sent','overdue','partial')), 0) AS total_pending
            FROM invoices WHERE client_id = :cid AND agency_id = :aid AND status != 'cancelled'
        ");
        $invoiceSummary->execute([':cid' => $clientId, ':aid' => $agencyId]);
        $invoiceSummary = $invoiceSummary->fetch(\PDO::FETCH_ASSOC);

        // Content plans
        $plans = $pdo->prepare("
            SELECT id, title, status, week_start, created_at
            FROM content_plans
            WHERE client_id = :cid AND agency_id = :aid
            ORDER BY created_at DESC LIMIT 20
        ");
        $plans->execute([':cid' => $clientId, ':aid' => $agencyId]);
        $plans = $plans->fetchAll(\PDO::FETCH_ASSOC);

        // Tasks
        $tasks = $pdo->prepare("
            SELECT t.id, t.title, t.status, t.priority, t.due_date,
                   u.name AS assigned_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.client_id = :cid AND t.agency_id = :aid
            ORDER BY t.due_date NULLS LAST, t.created_at DESC LIMIT 30
        ");
        $tasks->execute([':cid' => $clientId, ':aid' => $agencyId]);
        $tasks = $tasks->fetchAll(\PDO::FETCH_ASSOC);

        // Ad metrics for client in period
        $adAccountIds = $pdo->prepare("
            SELECT id FROM ad_accounts WHERE client_id = :cid AND agency_id = :aid
        ");
        $adAccountIds->execute([':cid' => $clientId, ':aid' => $agencyId]);
        $adAccountIds = array_column($adAccountIds->fetchAll(\PDO::FETCH_ASSOC), 'id');

        $adMetrics = null;
        if ($adAccountIds) {
            $placeholders = implode(',', array_fill(0, count($adAccountIds), '?'));
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(spend), 0)            AS spend,
                    COALESCE(SUM(impressions), 0)       AS impressions,
                    COALESCE(SUM(clicks), 0)            AS clicks,
                    COALESCE(SUM(conversions), 0)       AS conversions,
                    CASE WHEN SUM(spend) > 0
                         THEN SUM(conversion_value)/SUM(spend) ELSE 0 END AS roas
                FROM ad_daily_metrics
                WHERE ad_account_id IN ({$placeholders})
                  AND entity_type = 'campaign'
                  AND date BETWEEN ? AND ?
            ");
            $stmt->execute([...$adAccountIds, $since, $until]);
            $adMetrics = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        // Organic metrics for client in period
        $organicAccountIds = $pdo->prepare("
            SELECT id FROM organic_accounts WHERE client_id = :cid AND agency_id = :aid
        ");
        $organicAccountIds->execute([':cid' => $clientId, ':aid' => $agencyId]);
        $organicAccountIds = array_column($organicAccountIds->fetchAll(\PDO::FETCH_ASSOC), 'id');

        $organicMetrics = null;
        if ($organicAccountIds) {
            $placeholders = implode(',', array_fill(0, count($organicAccountIds), '?'));
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(reach), 0)       AS reach,
                    COALESCE(SUM(impressions), 0)  AS impressions,
                    COALESCE(SUM(likes + comments + shares + saves), 0) AS engagement,
                    COUNT(*)                        AS posts
                FROM organic_posts
                WHERE organic_account_id IN ({$placeholders})
                  AND posted_at::date BETWEEN ? AND ?
            ");
            $stmt->execute([...$organicAccountIds, $since, $until]);
            $organicMetrics = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        $agencyStmt = $pdo->prepare("SELECT name FROM agencies WHERE id = ?");
        $agencyStmt->execute([$agencyId]);
        $agency = $agencyStmt->fetch(\PDO::FETCH_ASSOC);

        return $this->view('executive.client_report', [
            'client'         => $client,
            'agency'         => $agency,
            'since'          => $since,
            'until'          => $until,
            'invoices'       => $invoices,
            'invoiceSummary' => $invoiceSummary,
            'plans'          => $plans,
            'tasks'          => $tasks,
            'adMetrics'      => $adMetrics,
            'organicMetrics' => $organicMetrics,
        ]);
    }
}
