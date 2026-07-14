<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Consultas do relatório executivo (visão da agência e por cliente).
 *
 * Sem tabela própria: agrega financeiro, conteúdo, tarefas, tráfego e orgânico.
 * Escopo de agência explícito em toda query (ARCH-01 — SQL fora do controller).
 */
class ExecutiveReportRepository extends Repository
{
    protected string $table = 'clients';

    // ── Visão da agência ──────────────────────────────────────────────────────

    /** Faturado x recebido por mês (últimos 12 meses). */
    public function revenueTrend(int $agencyId): array
    {
        return $this->all(
            "SELECT TO_CHAR(DATE_TRUNC('month', COALESCE(paid_at, due_date)), 'YYYY-MM') AS month,
                    COALESCE(SUM(amount_paid), 0) AS received,
                    COALESCE(SUM(total), 0)       AS billed
             FROM invoices
             WHERE agency_id = :aid
               AND status != 'cancelled'
               AND DATE_TRUNC('month', COALESCE(paid_at, due_date)) >= DATE_TRUNC('month', NOW() - INTERVAL '11 months')
             GROUP BY 1
             ORDER BY 1",
            [':aid' => $agencyId]
        );
    }

    /** Contadores de planos de conteúdo por status. */
    public function contentKpis(int $agencyId): array
    {
        return $this->first(
            "SELECT
                COUNT(*)                                              AS total,
                COUNT(*) FILTER (WHERE status = 'approved')           AS approved,
                COUNT(*) FILTER (WHERE status = 'sent')               AS awaiting,
                COUNT(*) FILTER (WHERE status IN ('draft','revision')) AS draft,
                COUNT(*) FILTER (WHERE status = 'revision')           AS revision
             FROM content_plans
             WHERE agency_id = :aid",
            [':aid' => $agencyId]
        ) ?? [];
    }

    /** KPIs de tráfego pago da agência no período. */
    public function adsKpis(int $agencyId, string $since, string $until): array
    {
        return $this->first(
            "SELECT
                COALESCE(SUM(m.spend), 0)       AS total_spend,
                COALESCE(SUM(m.impressions), 0) AS total_impressions,
                COALESCE(SUM(m.clicks), 0)      AS total_clicks,
                COALESCE(SUM(m.conversions), 0) AS total_conversions,
                CASE WHEN SUM(m.spend) > 0
                     THEN SUM(m.conversion_value) / SUM(m.spend) ELSE 0 END AS avg_roas
             FROM ad_daily_metrics m
             JOIN ad_accounts a ON a.id = m.ad_account_id AND a.agency_id = :aid
             WHERE m.entity_type = 'campaign'
               AND m.date BETWEEN :since AND :until",
            [':aid' => $agencyId, ':since' => $since, ':until' => $until]
        ) ?? [];
    }

    /** KPIs de orgânico da agência no período. */
    public function organicKpis(int $agencyId, string $since, string $until): array
    {
        return $this->first(
            "SELECT
                COALESCE(SUM(p.reach), 0)       AS total_reach,
                COALESCE(SUM(p.impressions), 0) AS total_impressions,
                COALESCE(SUM(p.likes + p.comments + p.shares + p.saves), 0) AS total_engagement,
                COUNT(*)                        AS total_posts
             FROM organic_posts p
             JOIN organic_accounts oa ON oa.id = p.organic_account_id AND oa.agency_id = :aid
             WHERE p.posted_at::date BETWEEN :since AND :until",
            [':aid' => $agencyId, ':since' => $since, ':until' => $until]
        ) ?? [];
    }

    /** Linha-resumo por cliente ativo (financeiro + conteúdo + tarefas). */
    public function clientSummary(int $agencyId): array
    {
        return $this->all(
            "SELECT
                c.id, c.name, c.status,
                COUNT(DISTINCT i.id)  FILTER (WHERE i.status NOT IN ('cancelled','draft')) AS invoices_count,
                COALESCE(SUM(i.total) FILTER (WHERE i.status NOT IN ('cancelled','draft')), 0) AS invoiced,
                COALESCE(SUM(i.amount_paid) FILTER (WHERE i.status NOT IN ('cancelled')), 0) AS paid,
                COALESCE(SUM(i.total - i.amount_paid) FILTER (WHERE i.status IN ('sent','overdue','partial')), 0) AS pending,
                COUNT(DISTINCT cp.id) FILTER (WHERE cp.status = 'sent')     AS plans_awaiting,
                COUNT(DISTINCT cp.id) FILTER (WHERE cp.status = 'approved') AS plans_approved,
                COUNT(DISTINCT t.id)  FILTER (WHERE t.status != 'done')     AS open_tasks
             FROM clients c
             LEFT JOIN invoices i      ON i.client_id = c.id  AND i.agency_id = c.agency_id
             LEFT JOIN content_plans cp ON cp.client_id = c.id AND cp.agency_id = c.agency_id
             LEFT JOIN tasks t          ON t.client_id = c.id  AND t.agency_id = c.agency_id
             WHERE c.agency_id = :aid AND c.status = 'active'
             GROUP BY c.id, c.name, c.status
             ORDER BY invoiced DESC, c.name",
            [':aid' => $agencyId]
        );
    }

    // ── Relatório de um cliente ───────────────────────────────────────────────

    public function clientInvoiceSummary(int $clientId, int $agencyId): array
    {
        return $this->first(
            "SELECT
                COALESCE(SUM(total), 0)       AS total_billed,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(total - amount_paid) FILTER (WHERE status IN ('sent','overdue','partial')), 0) AS total_pending
             FROM invoices
             WHERE client_id = :cid AND agency_id = :aid AND status != 'cancelled'",
            [':cid' => $clientId, ':aid' => $agencyId]
        ) ?? [];
    }

    public function clientPlans(int $clientId, int $agencyId, int $limit = 20): array
    {
        return $this->limited(
            "SELECT id, title, status, week_start, created_at
             FROM content_plans
             WHERE client_id = :cid AND agency_id = :aid
             ORDER BY created_at DESC
             LIMIT :lim",
            [':cid' => $clientId, ':aid' => $agencyId],
            $limit
        );
    }

    public function clientTasks(int $clientId, int $agencyId, int $limit = 30): array
    {
        return $this->limited(
            "SELECT t.id, t.title, t.status, t.priority, t.due_date, u.name AS assigned_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.client_id = :cid AND t.agency_id = :aid
             ORDER BY t.due_date NULLS LAST, t.created_at DESC
             LIMIT :lim",
            [':cid' => $clientId, ':aid' => $agencyId],
            $limit
        );
    }

    /**
     * Métricas de tráfego do cliente no período. Null quando o cliente **não
     * tem conta de anúncio** (a view esconde a seção); zeros quando tem conta
     * mas nada no período — mesma semântica de antes da extração.
     * O escopo de agência entra no JOIN: não confia só no client_id.
     */
    public function clientAdMetrics(int $clientId, int $agencyId, string $since, string $until): ?array
    {
        if (!$this->clientHasAccount('ad_accounts', $clientId, $agencyId)) {
            return null;
        }

        return $this->first(
            "SELECT
                COALESCE(SUM(m.spend), 0)       AS spend,
                COALESCE(SUM(m.impressions), 0) AS impressions,
                COALESCE(SUM(m.clicks), 0)      AS clicks,
                COALESCE(SUM(m.conversions), 0) AS conversions,
                CASE WHEN SUM(m.spend) > 0
                     THEN SUM(m.conversion_value) / SUM(m.spend) ELSE 0 END AS roas
             FROM ad_daily_metrics m
             JOIN ad_accounts a ON a.id = m.ad_account_id
                                AND a.client_id = :cid
                                AND a.agency_id = :aid
             WHERE m.entity_type = 'campaign'
               AND m.date BETWEEN :since AND :until",
            [':cid' => $clientId, ':aid' => $agencyId, ':since' => $since, ':until' => $until]
        );
    }

    /** Métricas de orgânico do cliente no período. Null se não tem conta (ver acima). */
    public function clientOrganicMetrics(int $clientId, int $agencyId, string $since, string $until): ?array
    {
        if (!$this->clientHasAccount('organic_accounts', $clientId, $agencyId)) {
            return null;
        }

        return $this->first(
            "SELECT
                COALESCE(SUM(p.reach), 0)       AS reach,
                COALESCE(SUM(p.impressions), 0) AS impressions,
                COALESCE(SUM(p.likes + p.comments + p.shares + p.saves), 0) AS engagement,
                COUNT(*)                        AS posts
             FROM organic_posts p
             JOIN organic_accounts oa ON oa.id = p.organic_account_id
                                      AND oa.client_id = :cid
                                      AND oa.agency_id = :aid
             WHERE p.posted_at::date BETWEEN :since AND :until",
            [':cid' => $clientId, ':aid' => $agencyId, ':since' => $since, ':until' => $until]
        );
    }

    /** O cliente tem alguma conta conectada nessa tabela? (allowlist de tabelas). */
    private function clientHasAccount(string $table, int $clientId, int $agencyId): bool
    {
        // Identificador dinâmico só por allowlist — nunca interpolar input.
        if (!in_array($table, ['ad_accounts', 'organic_accounts'], true)) {
            throw new \InvalidArgumentException("Tabela não permitida: {$table}");
        }

        return $this->first(
            "SELECT 1 FROM {$table} WHERE client_id = :cid AND agency_id = :aid LIMIT 1",
            [':cid' => $clientId, ':aid' => $agencyId]
        ) !== null;
    }

    /**
     * Helper: LIMIT precisa de bind tipado (PARAM_INT) — com
     * EMULATE_PREPARES=false, um bind de string quebra a query.
     */
    private function limited(string $sql, array $params, int $limit): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
