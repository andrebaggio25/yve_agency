<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Tabela `agencies` — o próprio tenant.
 *
 * Não usa `agencyScope()`: aqui a agência é a linha, não o filtro. Quem chama
 * é responsável por passar o ID certo (a sessão, no caso do tenant; o platform
 * admin, no caso do /admin). Existe para tirar o SQL dos controllers (ARCH-01).
 */
class AgencyRepository extends Repository
{
    protected string $table = 'agencies';

    /** Todos os campos da agência. */
    public function find(int $id): ?array
    {
        return $this->first("SELECT * FROM agencies WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    /** Só o essencial (uso em telas de admin que não precisam do resto). */
    public function findBasic(int $id): ?array
    {
        return $this->first("SELECT id, name FROM agencies WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    /** Lista para selects do painel da plataforma. */
    public function allForSelect(): array
    {
        return $this->all("SELECT id, name FROM agencies ORDER BY name");
    }

    // ── Painel da plataforma (/admin/tenants) ─────────────────────────────────

    /** Todos os tenants com contagem de usuários e clientes. */
    public function allWithCounts(): array
    {
        return $this->all(
            "SELECT a.*,
                    COUNT(DISTINCT u.id) AS user_count,
                    COUNT(DISTINCT c.id) AS client_count
             FROM agencies a
             LEFT JOIN users u   ON u.agency_id = a.id AND u.is_platform_admin = FALSE
             LEFT JOIN clients c ON c.agency_id = a.id
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
    }

    /** Cria o tenant e devolve o ID. */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO agencies (name, country, currency_code, timezone, status, slug, created_at)
             VALUES (:name, :country, :currency, :timezone, :status, :slug, NOW())
             RETURNING id"
        );
        $stmt->execute([
            ':name'     => $data['name'],
            ':country'  => $data['country']       ?? 'BR',
            ':currency' => $data['currency_code'] ?? 'BRL',
            ':timezone' => $data['timezone']      ?? 'America/Sao_Paulo',
            ':status'   => $data['status']        ?? 'active',
            ':slug'     => $data['slug'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** Atualiza os campos administráveis pelo platform admin. */
    public function updateAdmin(int $id, array $data): void
    {
        $this->query(
            "UPDATE agencies SET
                name          = :name,
                country       = :country,
                currency_code = :currency,
                timezone      = :timezone,
                status        = :status,
                updated_at    = NOW()
             WHERE id = :id",
            [
                ':name'     => $data['name'],
                ':country'  => $data['country']       ?? 'BR',
                ':currency' => $data['currency_code'] ?? 'BRL',
                ':timezone' => $data['timezone']      ?? 'America/Sao_Paulo',
                ':status'   => $data['status']        ?? 'active',
                ':id'       => $id,
            ]
        );
    }

    public function deleteById(int $id): void
    {
        $this->query("DELETE FROM agencies WHERE id = :id", [':id' => $id]);
    }

    /** Usuários (não-platform-admin) de um tenant, com o papel. */
    public function usersOf(int $agencyId): array
    {
        return $this->all(
            "SELECT u.id, u.name, u.email, u.status, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r       ON r.id = ur.role_id
             WHERE u.agency_id = :agency_id AND u.is_platform_admin = FALSE
             ORDER BY u.name",
            [':agency_id' => $agencyId]
        );
    }

    public function countUsers(int $agencyId): int
    {
        $row = $this->first(
            "SELECT COUNT(*) AS n FROM users WHERE agency_id = :id AND is_platform_admin = FALSE",
            [':id' => $agencyId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function slugExists(string $slug): bool
    {
        return $this->first("SELECT 1 FROM agencies WHERE slug = :slug LIMIT 1", [':slug' => $slug]) !== null;
    }

    // ── Tenant ────────────────────────────────────────────────────────────────

    /** Atualiza o cadastro da agência (tela de Configurações). */
    public function updateProfile(int $id, array $data): void
    {
        $this->query(
            "UPDATE agencies SET
                name            = :name,
                legal_name      = :legal_name,
                document_number = :doc_num,
                email           = :email,
                phone           = :phone,
                website         = :website,
                timezone        = :timezone,
                language        = :language,
                logo_url        = :logo_url,
                updated_at      = NOW()
             WHERE id = :id",
            [
                ':name'       => $data['name'],
                ':legal_name' => $data['legal_name'] ?? null,
                ':doc_num'    => $data['document_number'] ?? null,
                ':email'      => $data['email'] ?? null,
                ':phone'      => $data['phone'] ?? null,
                ':website'    => $data['website'] ?? null,
                ':timezone'   => $data['timezone'] ?? 'America/Sao_Paulo',
                ':language'   => $data['language'] ?? 'pt',
                ':logo_url'   => $data['logo_url'] ?? null,
                ':id'         => $id,
            ]
        );
    }
}
