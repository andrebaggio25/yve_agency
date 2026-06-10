<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Support\ActivityLogger;

class ClientService
{
    public function __construct(private readonly ClientRepository $clientRepo) {}

    public function listForUser(int $userId, int $agencyId, bool $canSeeAll): array
    {
        if ($canSeeAll) {
            return $this->clientRepo->findByAgency($agencyId);
        }
        return $this->clientRepo->findByUserAccess($userId, $agencyId);
    }

    public function findById(int $id, int $agencyId): ?array
    {
        return $this->clientRepo->findByIdAndAgency($id, $agencyId);
    }

    public function getContacts(int $clientId): array
    {
        return $this->clientRepo->findContacts($clientId);
    }

    public function getMarketingProfile(int $clientId): ?array
    {
        return $this->clientRepo->findMarketingProfile($clientId);
    }

    public function getFinancialProfile(int $clientId): ?array
    {
        return $this->clientRepo->findFinancialProfile($clientId);
    }

    public function getIntegrations(int $clientId): ?array
    {
        return $this->clientRepo->findIntegrations($clientId);
    }

    public function create(array $data, int $agencyId, int $createdBy): array
    {
        $errors = $this->validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $clientId = $this->clientRepo->insert([
            'agency_id'       => $agencyId,
            'name'            => trim($data['name']),
            'legal_name'      => $data['legal_name']   ?? null,
            'document_type'   => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'country'         => $data['country']      ?? 'BR',
            'state'           => $data['state']        ?? null,
            'city'            => $data['city']         ?? null,
            'address'         => $data['address']      ?? null,
            'postal_code'     => $data['postal_code']  ?? null,
            'language'        => $data['language']     ?? 'pt-BR',
            'timezone'        => $data['timezone']     ?? 'America/Sao_Paulo',
            'currency_code'   => $data['currency_code'] ?? 'BRL',
            'segment'         => $data['segment']      ?? null,
            'niche'           => $data['niche']        ?? null,
            'status'          => 'active',
            'start_date'      => $data['start_date']   ?? date('Y-m-d'),
            'manager_user_id' => $data['manager_user_id'] ?? $createdBy,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // Concede acesso ao criador
        $this->clientRepo->insertAccess((int) $clientId, $createdBy, 'admin');

        ActivityLogger::log('client_created', 'clients', null, (int) $clientId, ['name' => $data['name']]);

        return ['success' => true, 'id' => (int) $clientId];
    }

    public function update(int $clientId, array $data, int $agencyId): array
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return ['success' => false, 'errors' => ['id' => 'Cliente não encontrado.']];

        $errors = $this->validate($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $this->clientRepo->updateById($clientId, array_merge(
            array_filter($data, fn($v) => $v !== null),
            ['updated_at' => date('Y-m-d H:i:s')],
        ));

        ActivityLogger::log('client_updated', 'clients', null, $clientId);
        return ['success' => true];
    }

    public function delete(int $clientId, int $agencyId): void
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return;

        $this->clientRepo->updateById($clientId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        ActivityLogger::log('client_deleted', 'clients', null, $clientId);
    }

    public function listAccess(int $clientId, int $agencyId): array
    {
        return $this->clientRepo->findAccess($clientId, $agencyId);
    }

    public function listUsersForAccess(int $agencyId): array
    {
        return $this->clientRepo->findUsersByAgency($agencyId);
    }

    public function grantAccess(int $clientId, int $userId, string $level, int $agencyId): void
    {
        $this->clientRepo->upsertAccess($clientId, $userId, $level);
        ActivityLogger::log('client_access_granted', 'clients', null, $clientId, ['user_id' => $userId]);
    }

    public function revokeAccess(int $clientId, int $userId, int $agencyId): void
    {
        $this->clientRepo->deleteAccess($clientId, $userId);
        ActivityLogger::log('client_access_revoked', 'clients', null, $clientId, ['user_id' => $userId]);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Nome fantasia obrigatório.';
        return $errors;
    }
}
