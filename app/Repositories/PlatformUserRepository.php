<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Usuários vistos pelo painel da plataforma (/admin/usuarios) — cruzando
 * tenants. **Não** usa escopo de agência: quem chama já passou por
 * `Auth::requirePlatformAdmin()`. Nunca use este repositório em rota de tenant.
 *
 * Extraído dos controllers de admin (ARCH-01). Operações de tenant continuam
 * no `UserRepository`, que é escopado.
 */
class PlatformUserRepository extends Repository
{
    protected string $table = 'users';

    /** Lista global com tenant e papéis, filtrando por busca e/ou agência. */
    public function search(string $term = '', int $agencyId = 0): array
    {
        $where  = ['u.is_platform_admin = FALSE'];
        $params = [];

        if ($term !== '') {
            $where[]      = '(u.name ILIKE :q OR u.email ILIKE :q)';
            $params[':q'] = "%{$term}%";
        }
        if ($agencyId > 0) {
            $where[]        = 'u.agency_id = :aid';
            $params[':aid'] = $agencyId;
        }

        // $where só contém fragmentos fixos deste método — nada vem de input.
        return $this->all(
            "SELECT u.id, u.name, u.email, u.status, u.created_at,
                    a.name AS agency_name, a.id AS agency_id,
                    STRING_AGG(r.name, ', ' ORDER BY r.name) AS roles
             FROM users u
             LEFT JOIN agencies a    ON a.id = u.agency_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r       ON r.id = ur.role_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.id, a.name, a.id
             ORDER BY a.name, u.name",
            $params
        );
    }

    /** Usuário de tenant (nunca um platform admin) com o nome da agência. */
    public function find(int $id): ?array
    {
        return $this->first(
            "SELECT u.*, a.name AS agency_name
             FROM users u
             LEFT JOIN agencies a ON a.id = u.agency_id
             WHERE u.id = :id AND u.is_platform_admin = FALSE
             LIMIT 1",
            [':id' => $id]
        );
    }

    /** Papéis globais (agency_id NULL) disponíveis para atribuição. */
    public function globalRoles(): array
    {
        return $this->all("SELECT id, name, slug FROM roles WHERE agency_id IS NULL ORDER BY name");
    }

    /** IDs dos papéis de um usuário. */
    public function roleIdsOf(int $userId): array
    {
        $rows = $this->all("SELECT role_id FROM user_roles WHERE user_id = :id", [':id' => $userId]);
        return array_map('intval', array_column($rows, 'role_id'));
    }

    /** Cria o usuário e devolve o ID (RETURNING — não depende de lastInsertId). */
    public function create(int $agencyId, string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (agency_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:agency_id, :name, :email, :hash, 'active', NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([
            ':agency_id' => $agencyId,
            ':name'      => $name,
            ':email'     => $email,
            ':hash'      => $passwordHash,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function updateProfile(int $id, string $name, string $email, int $agencyId): void
    {
        $this->query(
            "UPDATE users SET name = :name, email = :email, agency_id = :agency_id, updated_at = NOW()
             WHERE id = :id",
            [':name' => $name, ':email' => $email, ':agency_id' => $agencyId, ':id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->query(
            "UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id",
            [':status' => $status, ':id' => $id]
        );
    }

    /** E-mail já usado por OUTRO usuário? (`$exceptId` = 0 checa todos). */
    public function emailTaken(string $email, int $exceptId = 0): bool
    {
        return $this->first(
            "SELECT 1 FROM users WHERE email = :email AND id != :id LIMIT 1",
            [':email' => $email, ':id' => $exceptId]
        ) !== null;
    }

    /** ID de um papel global pelo slug (ex.: super_admin). */
    public function roleIdBySlug(string $slug): ?int
    {
        $row = $this->first("SELECT id FROM roles WHERE slug = :slug LIMIT 1", [':slug' => $slug]);
        return $row ? (int) $row['id'] : null;
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $this->query(
            "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, NOW())",
            [':user_id' => $userId, ':role_id' => $roleId]
        );
    }

    // Transação exposta para o Service orquestrar criação de tenant + admin.
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollBack(): void         { $this->pdo->rollBack(); }
}
