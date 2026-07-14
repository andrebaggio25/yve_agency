<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class ContentPlanRepository extends Repository
{
    protected string $table = 'content_plans';

    public function allByAgency(int $agencyId, array $filters = []): array
    {
        $where  = ['cp.agency_id = :agency_id'];
        $params = [':agency_id' => $agencyId];

        if (!empty($filters['client_id'])) {
            $where[]              = 'cp.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'cp.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['week_start'])) {
            $where[]                = 'cp.week_start >= :week_start';
            $params[':week_start']  = $filters['week_start'];
        }

        $cond = implode(' AND ', $where);
        $sql  = "SELECT cp.*,
                        c.name   AS client_name,
                        u.name   AS created_by_name,
                        (SELECT COUNT(*) FROM content_plan_items i WHERE i.content_plan_id = cp.id) AS total_items,
                        (SELECT COUNT(*) FROM content_plan_items i WHERE i.content_plan_id = cp.id AND i.status = 'approved') AS approved_items
                 FROM content_plans cp
                 JOIN clients c ON c.id = cp.client_id
                 LEFT JOIN users u ON u.id = cp.created_by
                 WHERE {$cond}
                 ORDER BY cp.week_start DESC, cp.id DESC";

        return $this->all($sql, $params);
    }

    /**
     * Itens de conteúdo com data de publicação num intervalo (PROD-04).
     *
     * O calendário é a visão que faltava: ninguém planeja conteúdo em lista —
     * planeja olhando o mês, onde buracos e acúmulos ficam evidentes. Os dados
     * já existiam; faltava a forma de ver.
     *
     * @param array{client_id?:int} $filters
     */
    public function itemsBetween(int $agencyId, string $from, string $to, array $filters = []): array
    {
        $where  = [
            'cp.agency_id = :agency_id',
            'i.publish_date BETWEEN :from AND :to',
        ];
        $params = [':agency_id' => $agencyId, ':from' => $from, ':to' => $to];

        if (!empty($filters['client_id'])) {
            $where[]              = 'cp.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        return $this->all(
            "SELECT i.id, i.publish_date, i.publish_time, i.platform, i.content_type,
                    i.title, i.status, i.content_plan_id,
                    cp.title AS plan_title,
                    c.name   AS client_name,
                    u.name   AS assigned_name
             FROM content_plan_items i
             JOIN content_plans cp ON cp.id = i.content_plan_id
             JOIN clients c        ON c.id = cp.client_id
             LEFT JOIN users u     ON u.id = i.assigned_to
             WHERE " . implode(' AND ', $where) . "
             ORDER BY i.publish_date, i.publish_time NULLS LAST, i.sort_order",
            $params
        );
    }

    /** Clientes que JÁ têm plano na semana — o complemento é o radar de pauta faltando. */
    public function clientIdsWithPlanForWeek(int $agencyId, string $weekStart): array
    {
        $rows = $this->all(
            'SELECT DISTINCT client_id FROM content_plans WHERE agency_id = :agency_id AND week_start = :week_start',
            [':agency_id' => $agencyId, ':week_start' => $weekStart]
        );

        return array_map('intval', array_column($rows, 'client_id'));
    }

    /** Já existe plano do cliente para a semana? (guarda da auto-criação) */
    public function existsForClientWeek(int $clientId, string $weekStart): bool
    {
        return (bool) $this->first(
            'SELECT 1 AS found FROM content_plans WHERE client_id = :client_id AND week_start = :week_start LIMIT 1',
            [':client_id' => $clientId, ':week_start' => $weekStart]
        );
    }

    // ── Modelo semanal por cliente ─────────────────────────────────────────────

    /** Modelo semanal do cliente (um por cliente), com os itens já decodificados. */
    public function findTemplateByClient(int $clientId, int $agencyId): ?array
    {
        $row = $this->first(
            'SELECT * FROM content_plan_templates WHERE client_id = :client_id AND agency_id = :agency_id',
            [':client_id' => $clientId, ':agency_id' => $agencyId]
        );
        if (!$row) return null;

        $row['items'] = is_string($row['items']) ? (json_decode($row['items'], true) ?? []) : ($row['items'] ?? []);
        return $row;
    }

    /** Grava (ou substitui) o modelo do cliente — upsert pela unique de client_id. */
    public function saveTemplate(int $clientId, int $agencyId, array $items, ?int $userId): void
    {
        $this->query(
            "INSERT INTO content_plan_templates (agency_id, client_id, items, created_by, created_at, updated_at)
             VALUES (:agency_id, :client_id, :items, :created_by, NOW(), NOW())
             ON CONFLICT (client_id) DO UPDATE
                SET items = EXCLUDED.items, created_by = EXCLUDED.created_by, updated_at = NOW()",
            [
                ':agency_id'  => $agencyId,
                ':client_id'  => $clientId,
                ':items'      => json_encode($items),
                ':created_by' => $userId,
            ]
        );
    }

    /**
     * Itens do cliente no intervalo — só de planos que já chegaram a ele.
     * Rascunho NUNCA aparece no portal: o cliente não pode ver (nem aprovar)
     * o que a agência ainda está montando.
     */
    public function itemsBetweenForClient(int $clientId, int $agencyId, string $from, string $to): array
    {
        return $this->all(
            "SELECT i.id, i.publish_date, i.publish_time, i.platform, i.content_type,
                    i.title, i.status, i.content_plan_id,
                    cp.title AS plan_title
             FROM content_plan_items i
             JOIN content_plans cp ON cp.id = i.content_plan_id
             WHERE cp.client_id = :client_id AND cp.agency_id = :agency_id
               AND cp.status != 'draft'
               AND i.publish_date BETWEEN :from AND :to
             ORDER BY i.publish_date, i.publish_time NULLS LAST, i.sort_order",
            [':client_id' => $clientId, ':agency_id' => $agencyId, ':from' => $from, ':to' => $to]
        );
    }

    /**
     * Plano vizinho (semana anterior/seguinte) visível ao cliente — navegação
     * ← → do portal. $direction vem de allowlist no controller.
     */
    public function findAdjacentForClient(int $clientId, string $weekStart, string $direction): ?array
    {
        $op    = $direction === 'prev' ? '<' : '>';
        $order = $direction === 'prev' ? 'DESC' : 'ASC';

        return $this->first(
            "SELECT id, title, week_start, week_end
             FROM content_plans
             WHERE client_id = :client_id AND status != 'draft' AND week_start {$op} :ws
             ORDER BY week_start {$order} LIMIT 1",
            [':client_id' => $clientId, ':ws' => $weekStart]
        );
    }

    public function allByClient(int $clientId, int $agencyId): array
    {
        return $this->all(
            "SELECT cp.*,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM content_plan_items i WHERE i.content_plan_id = cp.id) AS total_items,
                    (SELECT COUNT(*) FROM content_plan_items i WHERE i.content_plan_id = cp.id AND i.status = 'approved') AS approved_items
             FROM content_plans cp
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE cp.client_id = :client_id AND cp.agency_id = :agency_id AND cp.status != 'draft'
             ORDER BY cp.week_start DESC",
            [':client_id' => $clientId, ':agency_id' => $agencyId]
        );
    }

    /**
     * LEFT JOIN em `users`: `created_by` **não tem FK**, então aponta para um
     * usuário que pode não existir mais (alguém saiu da equipe). Com INNER JOIN,
     * o plano sumia da tela — 404 num plano que existe e aparece na listagem.
     */
    public function findByIdFull(int $id, int $agencyId): ?array
    {
        return $this->first(
            "SELECT cp.*,
                    c.name         AS client_name,
                    c.timezone     AS client_timezone,
                    c.portal_token AS client_portal_token,
                    u.name         AS created_by_name
             FROM content_plans cp
             JOIN clients c ON c.id = cp.client_id
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE cp.id = :id AND cp.agency_id = :agency_id",
            [':id' => $id, ':agency_id' => $agencyId]
        );
    }

    public function findByIdForClient(int $id, int $clientId): ?array
    {
        return $this->first(
            "SELECT cp.*, c.name AS client_name, u.name AS created_by_name
             FROM content_plans cp
             JOIN clients c ON c.id = cp.client_id
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE cp.id = :id AND cp.client_id = :client_id AND cp.status != 'draft'",
            [':id' => $id, ':client_id' => $clientId]
        );
    }

    public function createPlan(array $data): int
    {
        $data = array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->insert($data);
    }

    public function updatePlan(int $id, int $agencyId, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->update($data, ['id' => $id, 'agency_id' => $agencyId]);
    }

    public function deletePlan(int $id, int $agencyId): int
    {
        return $this->delete(['id' => $id, 'agency_id' => $agencyId]);
    }

    // ── Items ──────────────────────────────────────────────────────────────────

    public function getItems(int $planId): array
    {
        return $this->all(
            "SELECT i.*,
                    u.name AS assigned_to_name,
                    (SELECT COUNT(*) FROM content_feedbacks f WHERE f.content_plan_item_id = i.id) AS feedback_count
             FROM content_plan_items i
             LEFT JOIN users u ON u.id = i.assigned_to
             WHERE i.content_plan_id = :plan_id
             ORDER BY i.sort_order ASC, i.publish_date ASC, i.created_at ASC",
            [':plan_id' => $planId]
        );
    }

    public function findItem(int $itemId, int $agencyId): ?array
    {
        return $this->first(
            "SELECT i.*, cp.agency_id
             FROM content_plan_items i
             JOIN content_plans cp ON cp.id = i.content_plan_id
             WHERE i.id = :id AND cp.agency_id = :agency_id",
            [':id' => $itemId, ':agency_id' => $agencyId]
        );
    }

    public function findItemForClient(int $itemId, int $clientId): ?array
    {
        return $this->first(
            "SELECT i.*, cp.status AS plan_status, cp.agency_id
             FROM content_plan_items i
             JOIN content_plans cp ON cp.id = i.content_plan_id
             WHERE i.id = :id AND i.client_id = :client_id AND cp.status != 'draft'",
            [':id' => $itemId, ':client_id' => $clientId]
        );
    }

    public function createItem(array $data): int
    {
        $now  = date('Y-m-d H:i:s');
        $cols = array_keys($data);
        $cols = array_merge($cols, ['created_at', 'updated_at']);
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", array_keys($data)));
        $colList      = implode(', ', array_keys($data));

        $stmt = $this->pdo->prepare("INSERT INTO content_plan_items ({$colList}) VALUES ({$placeholders}) RETURNING id");
        $stmt->execute($this->namedParams($data));
        return (int) ($stmt->fetchColumn() ?: $this->pdo->lastInsertId());
    }

    public function updateItem(int $itemId, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $setParts   = [];
        $params     = [];
        foreach ($data as $col => $val) {
            $setParts[]        = "{$col} = :set_{$col}";
            $params["set_{$col}"] = $val;
        }
        $params['where_id'] = $itemId;
        $sql = "UPDATE content_plan_items SET " . implode(', ', $setParts) . " WHERE id = :where_id";
        return $this->query($sql, $params)->rowCount();
    }

    public function deleteItem(int $itemId, int $agencyId): bool
    {
        $item = $this->findItem($itemId, $agencyId);
        if (!$item) return false;
        $stmt = $this->pdo->prepare("DELETE FROM content_plan_items WHERE id = :id");
        $stmt->execute([':id' => $itemId]);
        return $stmt->rowCount() > 0;
    }

    public function reorderItems(int $planId, array $orderedIds): void
    {
        $stmt = $this->pdo->prepare("UPDATE content_plan_items SET sort_order = :pos WHERE id = :id AND content_plan_id = :plan_id");
        foreach ($orderedIds as $pos => $id) {
            $stmt->execute([':pos' => $pos, ':id' => (int) $id, ':plan_id' => $planId]);
        }
    }

    // ── Feedbacks ─────────────────────────────────────────────────────────────

    public function getFeedbacks(int $itemId): array
    {
        return $this->all(
            "SELECT f.*,
                    u.name   AS user_name,
                    u.avatar AS user_avatar,
                    c.name   AS client_name
             FROM content_feedbacks f
             LEFT JOIN users   u ON u.id = f.user_id
             LEFT JOIN clients c ON c.id = f.client_id
             WHERE f.content_plan_item_id = :item_id
             ORDER BY f.created_at ASC",
            [':item_id' => $itemId]
        );
    }

    public function addFeedback(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $cols         = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", array_keys($data)));
        $stmt = $this->pdo->prepare("INSERT INTO content_feedbacks ({$cols}) VALUES ({$placeholders}) RETURNING id");
        $stmt->execute($this->namedParams($data));
        return (int) ($stmt->fetchColumn() ?: $this->pdo->lastInsertId());
    }

    public function getItemStatusSummary(int $planId): array
    {
        $rows = $this->all(
            "SELECT status, COUNT(*) as cnt FROM content_plan_items WHERE content_plan_id = :plan_id GROUP BY status",
            [':plan_id' => $planId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['status']] = (int) $r['cnt'];
        }
        return $map;
    }

    private function namedParams(array $data): array
    {
        $result = [];
        foreach ($data as $k => $v) {
            $result[str_starts_with($k, ':') ? $k : ":{$k}"] = $v;
        }
        return $result;
    }
}
