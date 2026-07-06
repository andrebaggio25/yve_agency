<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class InternalCommentRepository extends Repository
{
    protected string $table = 'internal_comments';

    public function getForEntity(string $type, int $entityId, int $agencyId): array
    {
        return $this->all(
            "SELECT ic.*, u.name AS user_name, u.avatar AS user_avatar
             FROM internal_comments ic
             LEFT JOIN users u ON u.id = ic.user_id
             WHERE ic.entity_type = :type
               AND ic.entity_id   = :entity_id
               AND ic.agency_id   = :agency_id
             ORDER BY ic.created_at ASC",
            [':type' => $type, ':entity_id' => $entityId, ':agency_id' => $agencyId]
        );
    }

    public function add(array $data): int
    {
        return (int) $this->insert($data);
    }

    /**
     * SEC-07: confirma que a entidade comentada pertence à agência, evitando
     * comentário em recurso de outro tenant (IDOR).
     */
    public function entityBelongsToAgency(string $type, int $entityId, int $agencyId): bool
    {
        $sql = match ($type) {
            'task'         => 'SELECT 1 FROM tasks WHERE id = :id AND agency_id = :a LIMIT 1',
            'content_plan' => 'SELECT 1 FROM content_plans WHERE id = :id AND agency_id = :a LIMIT 1',
            'content_plan_item' => 'SELECT 1 FROM content_plan_items i
                                      JOIN content_plans p ON p.id = i.content_plan_id
                                     WHERE i.id = :id AND p.agency_id = :a LIMIT 1',
            default => null,
        };

        if ($sql === null) {
            return false;
        }

        return $this->first($sql, [':id' => $entityId, ':a' => $agencyId]) !== null;
    }
}
