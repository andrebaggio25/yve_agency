<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class ClientRepository extends Repository
{
    protected string $table = 'clients';

    public function findByAgency(int $agencyId): array
    {
        return $this->all(
            'SELECT * FROM clients WHERE agency_id = :agency_id ORDER BY name',
            [':agency_id' => $agencyId],
        );
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first(
            'SELECT * FROM clients WHERE id = :id AND agency_id = :agency_id LIMIT 1',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    public function findByUserAccess(int $userId, int $agencyId): array
    {
        return $this->all("
            SELECT c.*
            FROM clients c
            JOIN client_user_access cua ON cua.client_id = c.id
            WHERE cua.user_id = :user_id AND c.agency_id = :agency_id
            ORDER BY c.name
        ", [':user_id' => $userId, ':agency_id' => $agencyId]);
    }

    public function findContacts(int $clientId): array
    {
        return $this->all(
            'SELECT * FROM client_contacts WHERE client_id = :client_id ORDER BY is_primary DESC, name',
            [':client_id' => $clientId],
        );
    }

    public function findMarketingProfile(int $clientId): ?array
    {
        return $this->first(
            'SELECT * FROM client_marketing_profiles WHERE client_id = :client_id LIMIT 1',
            [':client_id' => $clientId],
        );
    }

    public function findFinancialProfile(int $clientId): ?array
    {
        return $this->first(
            'SELECT * FROM client_financial_profiles WHERE client_id = :client_id LIMIT 1',
            [':client_id' => $clientId],
        );
    }

    public function findIntegrations(int $clientId): ?array
    {
        return $this->first(
            'SELECT * FROM client_integrations WHERE client_id = :client_id LIMIT 1',
            [':client_id' => $clientId],
        );
    }

    public function updateById(int $id, array $data): void
    {
        $this->update($data, ['id' => $id]);
    }

    // ── Access ────────────────────────────────────────────────────────────────

    public function insertAccess(int $clientId, int $userId, string $level): void
    {
        $this->query(
            'INSERT INTO client_user_access (client_id, user_id, access_level, created_at)
             VALUES (:cid, :uid, :level, NOW())',
            [':cid' => $clientId, ':uid' => $userId, ':level' => $level],
        );
    }

    public function upsertAccess(int $clientId, int $userId, string $level): void
    {
        $existing = $this->first(
            'SELECT id FROM client_user_access WHERE client_id = :cid AND user_id = :uid',
            [':cid' => $clientId, ':uid' => $userId],
        );

        if ($existing) {
            $this->query(
                'UPDATE client_user_access SET access_level = :level WHERE client_id = :cid AND user_id = :uid',
                [':level' => $level, ':cid' => $clientId, ':uid' => $userId],
            );
        } else {
            $this->insertAccess($clientId, $userId, $level);
        }
    }

    public function deleteAccess(int $clientId, int $userId): void
    {
        $this->query(
            'DELETE FROM client_user_access WHERE client_id = :cid AND user_id = :uid',
            [':cid' => $clientId, ':uid' => $userId],
        );
    }

    public function findAccess(int $clientId, int $agencyId): array
    {
        return $this->all("
            SELECT cua.*, u.name as user_name, u.email as user_email
            FROM client_user_access cua
            JOIN users u ON u.id = cua.user_id
            WHERE cua.client_id = :cid AND u.agency_id = :agency_id
            ORDER BY u.name
        ", [':cid' => $clientId, ':agency_id' => $agencyId]);
    }

    public function findUsersByAgency(int $agencyId): array
    {
        return $this->all(
            'SELECT id, name, email FROM users WHERE agency_id = :agency_id AND status = :status ORDER BY name',
            [':agency_id' => $agencyId, ':status' => 'active'],
        );
    }
}
