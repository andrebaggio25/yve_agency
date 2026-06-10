<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;
use App\Support\ActivityLogger;

class RoleService
{
    public function __construct(private readonly RoleRepository $roleRepo) {}

    public function list(?int $agencyId): array
    {
        return $this->roleRepo->findByAgency((int) $agencyId);
    }

    public function findById(int $id, ?int $agencyId): ?array
    {
        $role = $this->roleRepo->findByIdAndAgency($id, (int) $agencyId);
        if ($role) {
            $role['permissions'] = $this->roleRepo->findPermissions($id);
        }
        return $role;
    }

    public function create(array $data, array $permissionSlugs, ?int $agencyId): array
    {
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Nome obrigatório.';
        if (empty($data['slug'])) $errors['slug'] = 'Slug obrigatório.';
        if ($errors) return ['success' => false, 'errors' => $errors];

        $roleId = $this->roleRepo->insert([
            'agency_id'  => $agencyId,
            'name'       => trim($data['name']),
            'slug'       => trim($data['slug']),
            'description'=> $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->roleRepo->syncPermissions((int) $roleId, $permissionSlugs);

        ActivityLogger::log('role_created', 'roles', null, null, ['role_id' => $roleId]);
        return ['success' => true, 'id' => $roleId];
    }

    public function update(int $id, array $data, array $permissionSlugs, ?int $agencyId): array
    {
        $role = $this->roleRepo->findByIdAndAgency($id, (int) $agencyId);
        if (!$role) return ['success' => false, 'errors' => ['id' => 'Perfil não encontrado.']];

        $this->roleRepo->updateById($id, [
            'name'        => trim($data['name'] ?? $role['name']),
            'description' => $data['description'] ?? $role['description'],
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->roleRepo->syncPermissions($id, $permissionSlugs);

        ActivityLogger::log('role_updated', 'roles', null, null, ['role_id' => $id]);
        return ['success' => true];
    }

    public function delete(int $id, ?int $agencyId): void
    {
        $this->roleRepo->deleteByIdAndAgency($id, (int) $agencyId);
        ActivityLogger::log('role_deleted', 'roles', null, null, ['role_id' => $id]);
    }
}
