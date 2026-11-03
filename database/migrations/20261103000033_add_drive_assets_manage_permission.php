<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Decisão do dono (02/11): quem produz o material sobe na galeria sem precisar
 * poder mexer na planificação. O BUG-03 tinha colocado a escrita do Drive atrás
 * de `content.edit`, e o Designer, que não tem essa permissão, ficou só olhando.
 *
 * O Gestor de Tráfego fica de fora: ele só consulta o material.
 *
 * Quem tem `content.edit` continua escrevendo na galeria, pelo controller: perfil
 * criado pela agência não perde nada, e sem esta migration o Designer só segue em
 * leitura. Social Media e os administradores recebem a permissão para a tela de
 * perfis mostrar o que eles de fato podem fazer, e para o administrador conseguir
 * concedê-la (`Auth::canGrant`).
 *
 * Reversível: `down()` remove a permissão e, em cascata, as concessões.
 */
final class AddDriveAssetsManagePermission extends AbstractMigration
{
    private const ROLES = ['super_admin', 'agency_admin', 'social_media', 'designer'];

    public function up(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $pdo->exec(
            "INSERT INTO permissions (name, slug, module, description, created_at)
             VALUES ('Gerenciar arquivos do Drive', 'drive_assets.manage', 'drive_assets',
                     'Gerenciar arquivos do Drive', NOW())
             ON CONFLICT (slug) DO NOTHING"
        );

        $insert = $pdo->prepare(
            "INSERT INTO role_permissions (role_id, permission_id, created_at)
             SELECT r.id, p.id, NOW()
             FROM roles r
             JOIN permissions p ON p.slug = 'drive_assets.manage'
             WHERE r.slug = :role AND r.agency_id IS NULL
             ON CONFLICT DO NOTHING"
        );

        foreach (self::ROLES as $role) {
            $insert->execute([':role' => $role]);
        }
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        $pdo->exec(
            "DELETE FROM role_permissions
             WHERE permission_id = (SELECT id FROM permissions WHERE slug = 'drive_assets.manage')"
        );
        $pdo->exec("DELETE FROM permissions WHERE slug = 'drive_assets.manage'");
    }
}
