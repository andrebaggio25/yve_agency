<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\ActivityLogger;

class UserService
{
    public function __construct(private readonly UserRepository $userRepo) {}

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

        $userId = $this->userRepo->insert([
            'agency_id'     => $agencyId,
            'name'          => trim($data['name']),
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'phone'         => $data['phone'] ?? null,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('user_created', 'users', null, null, ['new_user_id' => $userId]);

        return ['success' => true, 'id' => $userId];
    }

    public function update(int $id, array $data, ?int $agencyId): array
    {
        $user = $this->userRepo->findByIdAndAgency($id, (int) $agencyId);
        if (!$user) return ['success' => false, 'errors' => ['id' => 'Usuário não encontrado.']];

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

        if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
            $this->userRepo->syncRoles($id, array_map('intval', $data['role_ids']));
        }

        ActivityLogger::log('user_updated', 'users', null, null, ['user_id' => $id]);
        return ['success' => true];
    }

    public function delete(int $id, ?int $agencyId): void
    {
        $this->userRepo->deleteByIdAndAgency($id, (int) $agencyId);
        ActivityLogger::log('user_deleted', 'users', null, null, ['user_id' => $id]);
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
