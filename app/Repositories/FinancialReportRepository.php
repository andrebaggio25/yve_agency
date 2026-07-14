<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Consultas de leitura dos relatórios financeiros (agregações por ano).
 *
 * Sem tabela própria: cruza invoices/payments/clients. Escopo de agência é
 * sempre explícito nos parâmetros (ARCH-01 — SQL fora dos controllers).
 */
class FinancialReportRepository extends Repository
{
    protected string $table = 'invoices';

    /** Receita e faturamento por cliente no ano (só clientes com faturamento). */
    public function revenueByClient(int $agencyId, string $year): array
    {
        return $this->all(
            "SELECT cl.name AS client_name,
                    COALESCE(SUM(p.amount), 0) AS received,
                    COALESCE(SUM(i.total), 0)  AS billed,
                    COUNT(DISTINCT i.id)       AS invoice_count
             FROM clients cl
             LEFT JOIN invoices i ON i.client_id = cl.id AND i.agency_id = :aid
                 AND i.status NOT IN ('cancelled','draft')
                 AND EXTRACT(YEAR FROM i.created_at) = :year
             LEFT JOIN payments p ON p.invoice_id = i.id
             WHERE cl.agency_id = :aid2
             GROUP BY cl.id, cl.name
             HAVING COALESCE(SUM(i.total), 0) > 0
             ORDER BY received DESC",
            [':aid' => $agencyId, ':year' => $year, ':aid2' => $agencyId]
        );
    }

    /** Faturas vencidas (enviadas ou em atraso, com vencimento no passado). */
    public function overdueInvoices(int $agencyId): array
    {
        return $this->all(
            "SELECT i.id, i.invoice_number, i.title, i.due_date,
                    i.total, i.amount_paid, (i.total - i.amount_paid) AS remaining,
                    cl.name AS client_name
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             WHERE i.agency_id = :aid AND i.status IN ('overdue','sent')
               AND i.due_date < CURRENT_DATE
             ORDER BY i.due_date ASC",
            [':aid' => $agencyId]
        );
    }

    /**
     * Receita recebida por mês do ano.
     * @return array<int,float> mapa 1..12 => total (meses sem pagamento = 0)
     */
    public function monthlyRevenue(int $agencyId, string $year): array
    {
        $rows = $this->all(
            "SELECT TO_CHAR(payment_date, 'YYYY-MM') AS month, SUM(amount) AS total
             FROM payments
             WHERE agency_id = :aid AND EXTRACT(YEAR FROM payment_date) = :year
             GROUP BY month
             ORDER BY month",
            [':aid' => $agencyId, ':year' => $year]
        );

        $map = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $map[(int) substr((string) $r['month'], 5, 2)] = (float) $r['total'];
        }

        return $map;
    }

    /** Indicadores do topo: recebido no ano, pendente total e nº de vencidas. */
    public function yearTotals(int $agencyId, string $year): array
    {
        return $this->first(
            "SELECT
                COALESCE(SUM(p.amount) FILTER (WHERE EXTRACT(YEAR FROM p.payment_date) = :year), 0) AS received_year,
                COALESCE(SUM(i.total - i.amount_paid) FILTER (WHERE i.status IN ('overdue','sent','partial')), 0) AS pending_total,
                COUNT(*) FILTER (WHERE i.status IN ('overdue','sent') AND i.due_date < CURRENT_DATE) AS overdue_count
             FROM invoices i
             JOIN agencies a ON a.id = i.agency_id AND a.id = :aid
             LEFT JOIN payments p ON p.invoice_id = i.id",
            [':aid' => $agencyId, ':year' => $year]
        ) ?? [];
    }
}
