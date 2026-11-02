<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;

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

        if ($agencyId !== null && $this->roleRepo->slugTaken(trim($data['slug']), $agencyId)) {
            return ['success' => false, 'errors' => ['slug' => t('roles.slug_taken')]];
        }

        if (!Auth::canGrant($permissionSlugs)) {
            return ['success' => false, 'errors' => ['permissions' => t('roles.cannot_grant')]];
        }

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

        // Perfil do sistema é compartilhado por todas as agências: editar aqui mudaria
        // o acesso de todas elas. Só o dono da plataforma mexe (decisão D10).
        if (!self::isEditable($role)) {
            return ['success' => false, 'errors' => ['id' => t('roles.system_readonly')]];
        }

        $added = array_values(array_diff($permissionSlugs, $this->roleRepo->permissionSlugs($id)));
        if (!Auth::canGrant($added)) {
            return ['success' => false, 'errors' => ['permissions' => t('roles.cannot_grant')]];
        }

        $this->roleRepo->updateById($id, [
            'name'        => trim($data['name'] ?? $role['name']),
            'description' => $data['description'] ?? $role['description'],
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->roleRepo->syncPermissions($id, $permissionSlugs);

        ActivityLogger::log('role_updated', 'roles', null, null, ['role_id' => $id]);
        return ['success' => true];
    }

    /** @param array<string, mixed> $role */
    public static function isEditable(array $role): bool
    {
        return $role['agency_id'] !== null || Auth::isPlatformAdmin();
    }

    public function delete(int $id, ?int $agencyId): void
    {
        $this->roleRepo->deleteByIdAndAgency($id, (int) $agencyId);
        ActivityLogger::log('role_deleted', 'roles', null, null, ['role_id' => $id]);
    }
}
