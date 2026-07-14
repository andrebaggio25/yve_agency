<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Support\ActivityLogger;

class ClientService
{
    public function __construct(
        private readonly ClientRepository $clientRepo,
        private readonly ?AutomationService $automations = null,
        private readonly ?NotificationService $notifications = null,
    ) {}

    public function listForUser(int $userId, int $agencyId, bool $canSeeAll): array
    {
        if ($canSeeAll) {
            return $this->clientRepo->findByAgency($agencyId);
        }
        return $this->clientRepo->findByUserAccess($userId, $agencyId);
    }

    public function listPaginated(int $agencyId, int $page = 1, int $perPage = 20, string $q = '', string $status = 'active'): array
    {
        return $this->clientRepo->findByAgencyPaginated($agencyId, $page, $perPage, $q, $status);
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

        $this->maybeOnboard($agencyId, (int) $clientId);

        return ['success' => true, 'id' => (int) $clientId];
    }

    /**
     * Automação client.onboarding: ao cadastrar o cliente, garante o portal_token
     * e envia a mensagem de boas-vindas. Gate por agência + idempotência.
     */
    private function maybeOnboard(int $agencyId, int $clientId): void
    {
        if (!$this->automations) return;
        if (!$this->automations->isEnabledForClient($agencyId, $clientId, 'client.onboarding')) return;

        $dedupe = "client:{$clientId}:onboarding";
        if (!$this->automations->shouldRun('client.onboarding', $dedupe)) return;

        $client = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client) return;

        if (empty($client['portal_token'])) {
            $token = bin2hex(random_bytes(16));
            $this->clientRepo->updateById($clientId, [
                'portal_token' => $token,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            $client['portal_token'] = $token;
        }

        $portalUrl = rtrim((string) env('APP_URL', ''), '/') . '/portal/' . $client['portal_token'];

        $this->notifications?->notifyEvent('client.onboarding', $agencyId, [
            'client'     => $client,
            'portal_url' => $portalUrl,
        ]);
        $this->automations->markRan($agencyId, $clientId, 'client.onboarding', $dedupe);
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

    public function setPortalAccess(int $clientId, int $agencyId, bool $enable): void
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return;

        if ($enable && empty($client['portal_token'])) {
            $this->clientRepo->updateById($clientId, [
                'portal_token' => bin2hex(random_bytes(16)),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        } elseif (!$enable && !empty($client['portal_token'])) {
            $this->clientRepo->updateById($clientId, [
                'portal_token' => null,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** Remove o vínculo da pasta do Drive (usado no "recriar pasta"). */
    public function clearDriveFolder(int $clientId, int $agencyId): void
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return;
        $this->clientRepo->updateById($clientId, ['drive_folder_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Arquiva o cliente (UX-02).
     *
     * A ação se chamava "excluir", mas **nunca excluiu**: marcava
     * `status = 'cancelled'`. Isso era bom (faturas e contratos são `RESTRICT`
     * — apagar de verdade falharia; planos e arquivos são `CASCADE` — sumiriam)
     * **mas a interface mentia**, e havia um furo real: o soft-delete não
     * desativava o portal, então o cliente "removido" **continuava entrando no
     * portal**, vendo faturas e enviando arquivos. Agora arquivar revoga o
     * acesso, que é o que qualquer pessoa espera de "remover".
     *
     * O histórico (faturas, contratos, planos, arquivos) é preservado de
     * propósito: é registro financeiro e de trabalho entregue.
     */
    public function archive(int $clientId, int $agencyId): void
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return;

        $this->clientRepo->updateById($clientId, [
            'status'         => 'cancelled',
            'portal_enabled' => false, // revoga o acesso do cliente ao portal
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('client_archived', 'clients', null, $clientId);
    }

    /** Reativa um cliente arquivado. O portal fica desligado até ser religado. */
    public function restore(int $clientId, int $agencyId): void
    {
        $client = $this->findById($clientId, $agencyId);
        if (!$client) return;

        $this->clientRepo->updateById($clientId, [
            'status'     => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('client_restored', 'clients', null, $clientId);
    }

    /**
     * O que existe vinculado ao cliente — para a tela de arquivamento mostrar o
     * impacto real em vez de um "tem certeza?" vazio.
     *
     * @return array{invoices:int,contracts:int,plans:int,files:int,tasks:int}
     */
    public function relatedCounts(int $clientId, int $agencyId): array
    {
        return $this->clientRepo->relatedCounts($clientId, $agencyId);
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
