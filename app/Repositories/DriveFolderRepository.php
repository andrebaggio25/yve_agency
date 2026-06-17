<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class DriveFolderRepository extends Repository
{
    protected string $table = 'drive_folders';

    public function create(array $data): int
    {
        return (int) $this->insert([
            'agency_id'       => $data['agency_id'],
            'client_id'       => $data['client_id'],
            'parent_id'       => $data['parent_id'] ?? null,
            'drive_folder_id' => $data['drive_folder_id'],
            'name'            => $data['name'],
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** Subpastas diretas de um pai (parent_id NULL = raiz do cliente). */
    public function children(int $clientId, ?int $parentId): array
    {
        if ($parentId === null) {
            return $this->all(
                "SELECT * FROM drive_folders
                 WHERE client_id = :c AND parent_id IS NULL
                 ORDER BY name",
                [':c' => $clientId]
            );
        }

        return $this->all(
            "SELECT * FROM drive_folders
             WHERE client_id = :c AND parent_id = :p
             ORDER BY name",
            [':c' => $clientId, ':p' => $parentId]
        );
    }

    public function findForClient(int $id, int $clientId): ?array
    {
        return $this->first(
            "SELECT * FROM drive_folders WHERE id = :id AND client_id = :c",
            [':id' => $id, ':c' => $clientId]
        );
    }
}
