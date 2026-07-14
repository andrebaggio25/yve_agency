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
