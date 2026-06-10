<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class RoleRepository extends Repository
{
    protected string $table = 'roles';

    public function findByAgency(int $agencyId): array
    {
        return $this->all(
            'SELECT * FROM roles WHERE agency_id = :agency_id OR agency_id IS NULL ORDER BY name',
            [':agency_id' => $agencyId],
        );
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first(
            'SELECT * FROM roles WHERE id = :id AND (agency_id = :agency_id OR agency_id IS NULL) LIMIT 1',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    public function updateById(int $id, array $data): void
    {
        $this->update($data, ['id' => $id]);
    }

    public function deleteByIdAndAgency(int $id, int $agencyId): void
    {
        $this->query(
            'DELETE FROM roles WHERE id = :id AND agency_id = :agency_id',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    public function findPermissions(int $roleId): array
    {
        return $this->all("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            ORDER BY p.module, p.slug
        ", [':role_id' => $roleId]);
    }

    public function syncPermissions(int $roleId, array $slugs): void
    {
        $this->query('DELETE FROM role_permissions WHERE role_id = :rid', [':rid' => $roleId]);

        foreach ($slugs as $slug) {
            $perm = $this->first(
                'SELECT id FROM permissions WHERE slug = :slug LIMIT 1',
                [':slug' => $slug],
            );

            if ($perm) {
                $this->query(
                    'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:rid, :pid, NOW())',
                    [':rid' => $roleId, ':pid' => $perm['id']],
                );
            }
        }
    }
}
