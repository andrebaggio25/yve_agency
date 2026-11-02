<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * BUG-04 — quem opera a tela interna de Aprovações precisa poder responder nela.
 *
 * O papel padrão **Social Media** tem `approvals.view` e nada mais: abre a tela,
 * vê o plano esperando a cliente e não consegue registrar a decisão — aprovar
 * ou pedir ajuste devolvia 403. É ele quem fala com a cliente (quase sempre por
 * WhatsApp) e quem precisa marcar na ferramenta o que foi combinado. O
 * **Designer** ganha só o comentário: opinião sobre o criativo é trabalho dele,
 * aprovar em nome da cliente não.
 *
 * `approvals.reject` fica de fora de propósito: a tela interna não oferece
 * reprovar (só aprovar, pedir revisão e comentar), e reprovar o criativo é
 * decisão da cliente — o portal dela já faz isso.
 *
 * Por que migration e não só seeder: `seedRoles()` faz `if ($exists) continue;`
 * — papel que já existe em produção nunca recebe permissão nova. O seeder
 * (`InitialDataSeeder::DEFAULT_ROLES`) cobre instalação nova; esta migration
 * cobre a base que já roda. Papéis padrão têm `agency_id IS NULL`, então a
 * concessão vale para todos os tenants; perfil criado pelo tenant fica de fora
 * (mesmo raciocínio da migration 30 — não há como distinguir perfil de equipe
 * de perfil feito para usuário de cliente).
 *
 * Sem tabela nem coluna nova: se o deploy acontecer antes de a migration rodar
 * pelo painel `/admin/migrations`, a tela só segue em leitura para esses dois
 * papéis, como hoje. Reversível: `down()` remove exatamente o que `up()` concede.
 */
final class GrantInternalApprovalToContentRoles extends AbstractMigration
{
    /** Slug do papel => slugs de permissão concedidos. */
    private const GRANTS = [
        'social_media' => ['approvals.comment', 'approvals.approve'],
        'designer'     => ['approvals.comment'],
    ];

    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $insert = $pdo->prepare(
            "INSERT INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, NOW()
             FROM roles r
             JOIN permissions p ON p.slug = :permission
             WHERE r.slug = :role AND r.agency_id IS NULL
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
               AND r.agency_id IS NULL
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
