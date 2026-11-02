<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Backfill que a migration 28 deixou faltando: `clients.view_all` para o papel
 * padrão **Gestor de Tráfego** (`traffic_manager`).
 *
 * A 28 cobriu `social_media`, `designer` e `financial`. O gestor de tráfego tem
 * `clients.view` e `content.view` no seeder, mas nenhuma das duas o faz
 * *alcançar* cliente: depois do SEC-11 (`canAccessClient` = `clients.view_all`
 * OU vínculo explícito em `client_user_access`) ele passou a ver lista de
 * clientes e módulo de Conteúdo vazios — o vínculo por cliente é manual e nada
 * no sistema o cria para a equipe da agência.
 *
 * Mesmo raciocínio da 28: o gestor cuida da carteira inteira da agência.
 *
 * Por que migration e não só seeder: `seedRoles()` faz `if ($exists) continue;`
 * — papel que já existe em produção nunca recebe permissão nova. O seeder
 * (`InitialDataSeeder::DEFAULT_ROLES`) cobre instalação nova; esta migration
 * cobre a base que já roda.
 *
 * Papéis padrão têm `agency_id IS NULL`, então a concessão vale para todos os
 * tenants. Reversível: `down()` remove exatamente o que `up()` concede.
 *
 * **Perfil criado pelo tenant (`agency_id NOT NULL`) fica fora de propósito.**
 * Não há como distinguir, pelas permissões, um perfil de equipe de um perfil
 * feito para usuário de cliente (os dois podem ter `content.view` e nenhum
 * `clients.*`) — conceder `clients.view_all` em massa daria a um usuário de
 * cliente o conteúdo de todos os clientes da agência, que é pior do que a tela
 * vazia. Para esses perfis o caminho é a tela de perfis (marcar "ver todos os
 * clientes") ou o vínculo por cliente; a lista de Conteúdo passou a dizer isso
 * ao usuário em vez de só vir vazia (`content.no_client_reach`).
 *
 * Sem tabela nem coluna nova — nada a quebrar se o deploy acontecer antes de a
 * migration rodar pelo painel `/admin/migrations`: o papel só segue sem alcance
 * de cliente, como está hoje.
 */
final class GrantClientsViewAllToTrafficManager extends AbstractMigration
{
    /** Slug do papel => slugs de permissão concedidos. */
    private const GRANTS = [
        'traffic_manager' => ['clients.view_all'],
    ];

    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

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
