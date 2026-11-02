<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class UserRepository extends Repository
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->first(
            'SELECT u.*, a.language AS agency_language
             FROM users u
             LEFT JOIN agencies a ON a.id = u.agency_id
             WHERE u.email = :email LIMIT 1',
            [':email' => strtolower($email)],
        );
    }

    public function emailExists(string $email): bool
    {
        $row = $this->first(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            [':email' => strtolower($email)],
        );
        return $row !== null;
    }

    public function findByAgency(int $agencyId): array
    {
        return $this->all(
            'SELECT id, name, email, phone, status, last_login_at, created_at
             FROM users WHERE agency_id = :agency_id ORDER BY name',
            [':agency_id' => $agencyId],
        );
    }

    public function findByIdAndAgency(int $id, int $agencyId): ?array
    {
        return $this->first(
            'SELECT * FROM users WHERE id = :id AND agency_id = :agency_id LIMIT 1',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->query(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            [':id' => $userId],
        );
    }

    public function updateById(int $id, array $data): void
    {
        $this->update($data, ['id' => $id]);
    }

    public function deleteByIdAndAgency(int $id, int $agencyId): void
    {
        $this->query(
            'DELETE FROM users WHERE id = :id AND agency_id = :agency_id',
            [':id' => $id, ':agency_id' => $agencyId],
        );
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    /** Returns flat array of permission slugs for the user */
    public function loadPermissions(int $userId): array
    {
        $rows = $this->all("
            SELECT DISTINCT p.slug
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id
            JOIN roles r ON r.id = ur.role_id
            JOIN users u ON u.id = ur.user_id
            WHERE ur.user_id = :user_id
              AND (r.agency_id IS NULL OR r.agency_id = u.agency_id)
        ", [':user_id' => $userId]);

        return array_column($rows, 'slug');
    }

    /**
     * IDs dos clientes que o usuário alcança por vínculo explícito.
     *
     * Vínculo só vale dentro da agência do usuário (SEC-11). Sem o JOIN, uma
     * linha cross-agência — gravada pelo furo que o SEC-11 fechou, ou sobrando
     * de uma troca de agência — entrava na sessão e quem lê `client_ids` cru
     * (ApprovalController) atravessava o tenant.
     */
    public function loadClientIds(int $userId): array
    {
        $rows = $this->all(
            'SELECT cua.client_id
               FROM client_user_access cua
               JOIN users u   ON u.id = cua.user_id
               JOIN clients c ON c.id = cua.client_id AND c.agency_id = u.agency_id
              WHERE cua.user_id = :user_id',
            [':user_id' => $userId],
        );
        return array_map(fn($r) => (int) $r['client_id'], $rows);
    }

    // ── Roles ─────────────────────────────────────────────────────────────────

    public function findRolesByAgency(int $agencyId): array
    {
        return $this->all(
            'SELECT * FROM roles WHERE agency_id = :agency_id OR agency_id IS NULL ORDER BY name',
            [':agency_id' => $agencyId],
        );
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->query('DELETE FROM user_roles WHERE user_id = :uid', [':uid' => $userId]);

        foreach ($roleIds as $roleId) {
            $this->query(
                'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())',
                [':uid' => $userId, ':rid' => $roleId],
            );
        }
    }

    // ── Password reset ────────────────────────────────────────────────────────

    /**
     * A validade é calculada no SQL, pelo mesmo relógio do `expires_at > NOW()`.
     * Com a hora vinda do PHP (fuso de São Paulo) contra o banco em UTC, o link já
     * nascia vencido.
     */
    public function savePasswordResetToken(int $userId, string $token, int $ttlMinutes = 60): void
    {
        $this->query('DELETE FROM password_reset_tokens WHERE user_id = :uid', [':uid' => $userId]);
        $this->query(
            'INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at)
             VALUES (:uid, :token, NOW() + make_interval(mins => :ttl), NOW())',
            [':uid' => $userId, ':token' => self::hashResetToken($token), ':ttl' => $ttlMinutes],
        );
    }

    public function findValidResetToken(string $token): ?array
    {
        return $this->first(
            "SELECT t.*, u.email
             FROM password_reset_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = :token AND t.expires_at > NOW() LIMIT 1",
            [':token' => self::hashResetToken($token)],
        );
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $this->query(
            'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id',
            [':hash' => $hash, ':id' => $userId],
        );
    }

    public function deleteResetToken(string $token): void
    {
        $this->query(
            'DELETE FROM password_reset_tokens WHERE token = :token',
            [':token' => self::hashResetToken($token)],
        );
    }

    /**
     * O banco guarda só o hash: quem lê um dump não consegue trocar a senha de ninguém.
     * SHA-256 basta aqui porque o token já é aleatório de 256 bits.
     */
    private static function hashResetToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Estado atual da conta para revalidar uma sessão aberta (AuthMiddleware).
     * Consulta por id de propósito: é o próprio usuário da sessão, antes de qualquer escopo.
     *
     * @return array{password_hash: string, status: string}|null
     */
    public function sessionState(int $userId): ?array
    {
        return $this->first(
            'SELECT password_hash, status FROM users WHERE id = :id LIMIT 1',
            [':id' => $userId],
        );
    }

    /** Find all active users in an agency that have a given permission slug. */
    public function findByAgencyAndPermission(int $agencyId, string $permissionSlug): array
    {
        return $this->all(
            "SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.agency_id = :agency_id
               AND u.status = 'active'
               AND p.slug = :slug",
            [':agency_id' => $agencyId, ':slug' => $permissionSlug],
        );
    }
}
