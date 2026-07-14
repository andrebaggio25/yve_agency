<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class ClientRepository extends Repository
{
    protected string $table = 'clients';

    /**
     * Clientes da agência para **uso operacional** (selects, filtros, listas de
     * trabalho): exclui os arquivados.
     *
     * Cliente arquivado não pode aparecer em seletor — você não cria fatura,
     * tarefa ou plano para um cliente que encerrou (UX-02). Para relatórios
     * históricos, que precisam do arquivado, use `findByAgencyIncludingArchived`.
     */
    public function findByAgency(int $agencyId): array
    {
        return $this->all(
            "SELECT * FROM clients
             WHERE agency_id = :agency_id AND (status IS NULL OR status <> 'cancelled')
             ORDER BY name",
            [':agency_id' => $agencyId],
        );
    }

    /** Inclui arquivados — só para histórico/relatório, nunca para seletor. */
    public function findByAgencyIncludingArchived(int $agencyId): array
    {
        return $this->all(
            'SELECT * FROM clients WHERE agency_id = :agency_id ORDER BY name',
            [':agency_id' => $agencyId],
        );
    }

    public function findByAgencyPaginated(int $agencyId, int $page = 1, int $perPage = 20, string $q = '', string $status = 'active'): array
    {
        $params = [':agency_id' => $agencyId];
        $where  = 'agency_id = :agency_id';
        if ($status === 'active') {
            $where .= " AND status = 'active'";
        } elseif ($status === 'inactive') {
            $where .= " AND (status IS NULL OR status <> 'active')";
        }
        if ($q) {
            $where .= ' AND (name ILIKE :q OR legal_name ILIKE :q)';
            $params[':q'] = "%{$q}%";
        }
        return $this->paginate(
            "SELECT * FROM clients WHERE {$where} ORDER BY name",
            $params, $page, $perPage
        );
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first(
            'SELECT * FROM clients WHERE id = :id AND agency_id = :agency_id LIMIT 1',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    /** Clientes que o usuário acessa — também sem arquivados (ver findByAgency). */
    public function findByUserAccess(int $userId, int $agencyId): array
    {
        return $this->all("
            SELECT c.*
            FROM clients c
            JOIN client_user_access cua ON cua.client_id = c.id
            WHERE cua.user_id = :user_id AND c.agency_id = :agency_id
              AND (c.status IS NULL OR c.status <> 'cancelled')
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

    // ── Portal ────────────────────────────────────────────────────────────────

    /**
     * Resolve o cliente pelo token do portal.
     *
     * Filtra `status <> 'cancelled'`: cliente arquivado **não entra no portal**.
     * Antes, o soft-delete não mexia no portal e o cliente "removido" continuava
     * acessando faturas e enviando arquivos (UX-02). O `portal_enabled` também é
     * desligado ao arquivar — este filtro é a segunda camada, para o caso de
     * alguém religar o portal de um cliente arquivado sem perceber.
     */
    public function findByPortalToken(string $token): ?array
    {
        return $this->first(
            "SELECT * FROM clients WHERE portal_token = :token AND (status IS NULL OR status <> 'cancelled') LIMIT 1",
            [':token' => $token],
        );
    }

    /**
     * Contadores do que está vinculado ao cliente (UX-02 — mostrar o impacto
     * real antes de arquivar, em vez de um "tem certeza?" sem informação).
     *
     * @return array{invoices:int,contracts:int,plans:int,files:int,tasks:int}
     */
    public function relatedCounts(int $clientId, int $agencyId): array
    {
        $row = $this->first(
            "SELECT
                (SELECT COUNT(*) FROM invoices       WHERE client_id = :c1 AND agency_id = :a1) AS invoices,
                (SELECT COUNT(*) FROM contracts      WHERE client_id = :c2 AND agency_id = :a2) AS contracts,
                (SELECT COUNT(*) FROM content_plans  WHERE client_id = :c3 AND agency_id = :a3) AS plans,
                (SELECT COUNT(*) FROM drive_files    WHERE client_id = :c4 AND agency_id = :a4) AS files,
                (SELECT COUNT(*) FROM tasks          WHERE client_id = :c5 AND agency_id = :a5) AS tasks",
            [
                ':c1' => $clientId, ':a1' => $agencyId,
                ':c2' => $clientId, ':a2' => $agencyId,
                ':c3' => $clientId, ':a3' => $agencyId,
                ':c4' => $clientId, ':a4' => $agencyId,
                ':c5' => $clientId, ':a5' => $agencyId,
            ]
        ) ?? [];

        return [
            'invoices'  => (int) ($row['invoices']  ?? 0),
            'contracts' => (int) ($row['contracts'] ?? 0),
            'plans'     => (int) ($row['plans']     ?? 0),
            'files'     => (int) ($row['files']     ?? 0),
            'tasks'     => (int) ($row['tasks']     ?? 0),
        ];
    }

    public function regeneratePortalToken(int $id): string
    {
        $token = bin2hex(random_bytes(32));
        $this->query(
            'UPDATE clients SET portal_token = :token WHERE id = :id',
            [':token' => $token, ':id' => $id],
        );
        return $token;
    }

    public function setPortalEnabled(int $id, bool $enabled): void
    {
        $this->query(
            'UPDATE clients SET portal_enabled = :enabled WHERE id = :id',
            [':enabled' => $enabled, ':id' => $id],
        );
    }

    /**
     * Todos os clientes (de todas as agências) que têm pasta no Drive.
     * Usado pelo cron de sincronização — sem escopo de agência (contexto sem sessão).
     * @return array<int,array{id:int,agency_id:int}>
     */
    public function allWithDriveFolder(): array
    {
        return $this->all(
            "SELECT id, agency_id FROM clients
             WHERE drive_folder_id IS NOT NULL AND drive_folder_id <> ''
               AND status = 'active'"
        );
    }
}
