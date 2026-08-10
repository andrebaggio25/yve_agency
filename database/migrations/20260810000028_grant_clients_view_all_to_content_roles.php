<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Backfill de RBAC para o acesso a cliente:
 *
 *  - `social_media` e `designer` ganham `clients.view_all` — sem isso não
 *    alcançam a galeria de Drive do cliente (`/clientes/{id}/conteudos`), que
 *    passa pelo ClientAccessMiddleware, e a lista de clientes vem vazia.
 *  - `financial` ganha `clients.view_all` e `clients.view_basic` — o relatório
 *    executivo por cliente (e o PDF) passou a exigir acesso ao cliente, e o
 *    financeiro fatura a carteira inteira.
 *
 * `clients.view_basic` estava no seeder mas **não existia no catálogo**
 * (config/permissions.php). Como `seedRoles()` só concede slug encontrado em
 * `permissions` (`if ($perm)`), a permissão nunca chegou ao banco: o papel
 * Financeiro ficou sem nenhuma permissão de cliente. Aqui ela é criada.
 *
 * Por que migration e não seeder: `seedRoles()` faz `if ($exists) continue;` —
 * papel que já existe em produção nunca recebe permissão nova. O seeder cobre
 * instalação nova; esta migration cobre a base que já roda.
 *
 * Papéis padrão têm `agency_id IS NULL` (compartilhados por todas as agências),
 * então a concessão vale para todos os tenants — mesmo alcance do seeder.
 *
 * Reversível: `down()` remove exatamente as concessões que `up()` faz.
 */
final class GrantClientsViewAllToContentRoles extends AbstractMigration
{
    /**
     * Concessões desta migration: slug do papel => slugs de permissão.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'social_media' => ['clients.view_all'],
        'designer'     => ['clients.view_all'],
        'financial'    => ['clients.view_all', 'clients.view_basic'],
    ];

    /**
     * Permissões que podem não existir ainda em `permissions`.
     *
     * @var array<string, array{name:string, module:string}>
     */
    private const ENSURE_PERMISSIONS = [
        'clients.view_all'   => ['name' => 'Ver todos os clientes',        'module' => 'clients'],
        'clients.view_basic' => ['name' => 'Ver dados básicos do cliente', 'module' => 'clients'],
    ];

    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $ensure = $pdo->prepare(
            "INSERT INTO permissions (name, slug, module, description, created_at)
             VALUES (:name, :slug, :module, :description, NOW())
             ON CONFLICT (slug) DO NOTHING"
        );
        foreach (self::ENSURE_PERMISSIONS as $slug => $meta) {
            $ensure->execute([
                ':name'        => $meta['name'],
                ':slug'        => $slug,
                ':module'      => $meta['module'],
                ':description' => $meta['name'],
            ]);
        }

        $insert = $pdo->prepare(
            "INSERT INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, NOW()
             FROM roles r
             JOIN permissions p ON p.slug = :permission
             WHERE r.slug = :role
             ON CONFLICT DO NOTHING"
        );

        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            foreach ($permissionSlugs as $permissionSlug) {
                $insert->execute([':permission' => $permissionSlug, ':role' => $roleSlug]);
            }
        }
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $delete = $pdo->prepare(
            "DELETE FROM role_permissions rp
             USING roles r, permissions p
             WHERE rp.role_id = r.id
               AND rp.permission_id = p.id
               AND p.slug = :permission
               AND r.slug = :role"
        );

        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            foreach ($permissionSlugs as $permissionSlug) {
                $delete->execute([':permission' => $permissionSlug, ':role' => $roleSlug]);
            }
        }
    }
}
