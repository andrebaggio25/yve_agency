<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\ActivityLogger;
use App\Support\Auth;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly RoleRepository $roleRepo,
    ) {}

    public function listForAgency(?int $agencyId): array
    {
        return $this->userRepo->findByAgency((int) $agencyId);
    }

    public function findById(int $id, ?int $agencyId): ?array
    {
        return $this->userRepo->findByIdAndAgency($id, (int) $agencyId);
    }

    public function listRoles(?int $agencyId): array
    {
        return $this->userRepo->findRolesByAgency((int) $agencyId);
    }

    public function create(array $data, ?int $agencyId): array
    {
        $errors = $this->validateCreate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        if ($this->userRepo->emailExists($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'E-mail já cadastrado.']];
        }

        $roleIds = !empty($data['role_id']) ? [(int) $data['role_id']] : [];
        $roleError = $this->roleAssignmentError($roleIds, $agencyId);
        if ($roleError !== null) {
            return ['success' => false, 'errors' => ['role_id' => $roleError]];
        }

        $userId = $this->userRepo->insert([
            'agency_id'     => $agencyId,
            'name'          => trim($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'phone'         => $data['phone'] ?? null,
            'status'        => $data['status'] ?? 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        if (!empty($data['role_id'])) {
            $this->userRepo->syncRoles((int) $userId, [(int) $data['role_id']]);
        }

        ActivityLogger::log('user_created', 'users', null, null, ['new_user_id' => $userId]);

        return ['success' => true, 'id' => $userId];
    }

    public function update(int $id, array $data, ?int $agencyId): array
    {
        $user = $this->userRepo->findByIdAndAgency($id, (int) $agencyId);
        if (!$user) return ['success' => false, 'errors' => ['id' => 'Usuário não encontrado.']];

        // Sem isto, quem tem users.edit troca a senha do admin e entra no lugar dele.
        if (!Auth::canGrant($this->userRepo->loadPermissions($id))) {
            return ['success' => false, 'errors' => ['id' => t('users.outranks_you')]];
        }

        // O formulário manda role_id; role_ids é a forma em lista.
        $roleIds = null;
        if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
            $roleIds = array_map('intval', $data['role_ids']);
        } elseif (!empty($data['role_id'])) {
            $roleIds = [(int) $data['role_id']];
        }

        if ($roleIds !== null) {
            $roleError = $this->roleAssignmentError($roleIds, $agencyId);
            if ($roleError !== null) {
                return ['success' => false, 'errors' => ['role_id' => $roleError]];
            }
        }

        $update = [
            'name'       => trim($data['name']   ?? $user['name']),
            'phone'      => $data['phone']        ?? $user['phone'],
            'status'     => $data['status']       ?? $user['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'errors' => ['password' => 'Senha deve ter pelo menos 8 caracteres.']];
            }
            $update['password_hash'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        $this->userRepo->updateById($id, $update);

        if ($roleIds !== null) {
            $this->userRepo->syncRoles($id, $roleIds);
        }

        ActivityLogger::log('user_updated', 'users', null, null, ['user_id' => $id]);
        return ['success' => true];
    }

    public function delete(int $id, ?int $agencyId): array
    {
        if (!Auth::canGrant($this->userRepo->loadPermissions($id))) {
            return ['success' => false, 'errors' => ['id' => t('users.outranks_you')]];
        }

        $this->userRepo->deleteByIdAndAgency($id, (int) $agencyId);
        ActivityLogger::log('user_deleted', 'users', null, null, ['user_id' => $id]);

        return ['success' => true];
    }

    /**
     * O perfil precisa ser do sistema ou da própria agência, e quem atribui precisa
     * ter tudo o que o perfil dá.
     *
     * @param list<int> $roleIds
     */
    private function roleAssignmentError(array $roleIds, ?int $agencyId): ?string
    {
        foreach ($roleIds as $roleId) {
            if ($this->roleRepo->findByIdAndAgency($roleId, (int) $agencyId) === null) {
                return t('roles.invalid');
            }
            if (!Auth::canGrant($this->roleRepo->permissionSlugs($roleId))) {
                return t('roles.cannot_grant');
            }
        }

        return null;
    }

    private function validateCreate(array $data): array
    {
        $errors = [];
        if (empty($data['name']))     $errors['name']     = 'Nome obrigatório.';
        if (empty($data['email']))    $errors['email']    = 'E-mail obrigatório.';
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';
        if (empty($data['password'])) $errors['password'] = 'Senha obrigatória.';
        if (strlen($data['password'] ?? '') < 8) $errors['password'] = 'Senha deve ter pelo menos 8 caracteres.';
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'As senhas não coincidem.';
        }
        return $errors;
    }
}
