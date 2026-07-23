<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Consultas de leitura do painel inicial da agência.
 *
 * Não tem tabela própria: agrega contadores de clientes/planos. Existe para
 * tirar o SQL do controller (ARCH-01) — a invariante do projeto é que toda
 * query mora num Repository, com escopo explícito de agência.
 */
class DashboardRepository extends Repository
{
    /**
     * Contadores do topo do painel, em um único round-trip.
     *
     * @return array{active_clients:int,pending_plans:int,pending_approvals:int}
     */
    public function statsByAgency(int $agencyId): array
    {
        $row = $this->first(
            "SELECT
                (SELECT COUNT(*) FROM clients       WHERE agency_id = :a1 AND status = 'active')                 AS active_clients,
                (SELECT COUNT(*) FROM content_plans WHERE agency_id = :a2 AND status IN ('draft','revision'))    AS pending_plans,
                (SELECT COUNT(*) FROM content_plans WHERE agency_id = :a3 AND status = 'sent')                   AS pending_approvals",
            [':a1' => $agencyId, ':a2' => $agencyId, ':a3' => $agencyId]
        ) ?? [];

        return [
            'active_clients'    => (int) ($row['active_clients']    ?? 0),
            'pending_plans'     => (int) ($row['pending_plans']     ?? 0),
            'pending_approvals' => (int) ($row['pending_approvals'] ?? 0),
        ];
    }

    /**
     * "Meu dia" (PROD-08): planos enviados ao cliente e sem resposta há
     * :days dias ou mais — aprovação parada é dinheiro parado.
     *
     * @return list<array<string,mixed>>
     */
    public function stalledApprovals(int $agencyId, int $days = 3, int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cp.id, cp.title, cp.sent_at, c.name AS client_name,
                    EXTRACT(DAY FROM NOW() - cp.sent_at)::int AS days_waiting
             FROM content_plans cp
             JOIN clients c ON c.id = cp.client_id
             WHERE cp.agency_id = :aid AND cp.status = 'sent'
               AND cp.sent_at IS NOT NULL
               AND cp.sent_at < NOW() - (:d || ' days')::interval
             ORDER BY cp.sent_at ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':aid', $agencyId, \PDO::PARAM_INT);
        $stmt->bindValue(':d', (string) $days);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * "Meu dia": faturas em aberto já vencidas ou vencendo nos próximos
     * :days dias (status que ainda espera dinheiro: sent/partial/overdue).
     *
     * @return list<array<string,mixed>>
     */
    public function invoicesNeedingAttention(int $agencyId, int $days = 7, int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.id, i.invoice_number, i.title, i.due_date, i.total, i.amount_paid,
                    i.status, c.name AS client_name,
                    (i.due_date < CURRENT_DATE)               AS is_overdue,
                    (i.due_date - CURRENT_DATE)               AS days_until_due
             FROM invoices i
             JOIN clients c ON c.id = i.client_id
             WHERE i.agency_id = :aid
               AND i.status IN ('sent', 'partial', 'overdue')
               AND i.due_date IS NOT NULL
               AND i.due_date <= CURRENT_DATE + (:d || ' days')::interval
             ORDER BY i.due_date ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':aid', $agencyId, \PDO::PARAM_INT);
        $stmt->bindValue(':d', (string) $days);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * "Meu dia": tarefas em aberto com prazo estourado.
     *
     * @return list<array<string,mixed>>
     */
    public function overdueTasks(int $agencyId, int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.title, t.due_date, t.status, t.priority,
                    c.name AS client_name, u.name AS assigned_name,
                    (CURRENT_DATE - t.due_date) AS days_late
             FROM tasks t
             LEFT JOIN clients c ON c.id = t.client_id
             LEFT JOIN users   u ON u.id = t.assigned_to
             WHERE t.agency_id = :aid
               AND t.status <> 'done'
               AND t.due_date IS NOT NULL
               AND t.due_date < CURRENT_DATE
             ORDER BY t.due_date ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':aid', $agencyId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * "Meu dia": contas de tráfego/orgânico ativas com sync parado há mais de
     * :hours horas — versão com escopo de agência do check global do /api/health.
     *
     * @return list<array<string,mixed>>
     */
    public function brokenSyncs(int $agencyId, int $hours = 48): array
    {
        return $this->all(
            "SELECT 'ads' AS kind, a.id, a.name, a.last_synced_at
             FROM ad_accounts a
             WHERE a.agency_id = :a1 AND a.status = 'active'
               AND (a.last_synced_at IS NULL OR a.last_synced_at < NOW() - (:h1 || ' hours')::interval)
             UNION ALL
             SELECT 'organic' AS kind, o.id, o.username AS name, o.last_synced_at
             FROM organic_accounts o
             WHERE o.agency_id = :a2 AND o.status = 'active'
               AND (o.last_synced_at IS NULL OR o.last_synced_at < NOW() - (:h2 || ' hours')::interval)
             ORDER BY last_synced_at NULLS FIRST",
            [':a1' => $agencyId, ':h1' => (string) $hours, ':a2' => $agencyId, ':h2' => (string) $hours]
        );
    }

    /** Últimos planos de conteúdo da agência (com o nome do cliente). */
    public function recentPlans(int $agencyId, int $limit = 5): array
    {
        // LIMIT precisa de bind tipado (PARAM_INT) — mesmo padrão do paginate()
        // da base; com EMULATE_PREPARES=false, um bind de string quebraria.
        $stmt = $this->pdo->prepare(
            "SELECT cp.id, cp.title, cp.status, cp.week_start, c.name AS client_name
             FROM content_plans cp
             JOIN clients c ON c.id = cp.client_id
             WHERE cp.agency_id = :aid
             ORDER BY cp.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':aid', $agencyId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
