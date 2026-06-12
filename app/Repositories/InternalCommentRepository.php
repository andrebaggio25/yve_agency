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
}
