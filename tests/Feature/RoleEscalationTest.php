<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\UserRepository;

/**
 * SEC-12: perfil do sistema é fixo (decisão D10), perfil vale só na própria agência
 * e ninguém concede o que não tem.
 */
final class RoleEscalationTest extends FeatureTestCase
{
    private const ADMIN_PERMS = ['users.view', 'users.edit', 'users.delete', 'roles.view', 'roles.edit', 'roles.create', 'settings.manage'];

    protected function tearDown(): void
    {
        // Roles e permissões não entram no TRUNCATE da base: cada teste limpa o que criou.
        if (isset($this->pdo)) {
            $this->pdo->exec("DELETE FROM roles WHERE slug LIKE 'teste-%'");
            $this->pdo->exec("DELETE FROM permissions WHERE slug LIKE 'teste.%'");
        }
        parent::tearDown();
    }

    private function createPermission(string $slug): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO permissions (name, slug, module, created_at) VALUES (:n, :s, 'teste', NOW()) RETURNING id"
        );
        $stmt->execute([':n' => $slug, ':s' => $slug]);
        return (int) $stmt->fetchColumn();
    }

    /** @param list<int> $permissionIds */
    private function createRole(?int $agencyId, string $slug, array $permissionIds = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (agency_id, name, slug, created_at, updated_at)
             VALUES (:a, :n, :s, NOW(), NOW()) RETURNING id'
        );
        $stmt->execute([':a' => $agencyId, ':n' => $slug, ':s' => $slug]);
        $roleId = (int) $stmt->fetchColumn();

        foreach ($permissionIds as $permissionId) {
            $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES ({$roleId}, {$permissionId}, NOW())");
        }

        return $roleId;
    }

    private function roleName(int $roleId): string
    {
        return (string) $this->pdo->query("SELECT name FROM roles WHERE id = {$roleId}")->fetchColumn();
    }

    /** @return list<int> */
    private function rolesOf(int $userId): array
    {
        $rows = $this->pdo->query("SELECT role_id FROM user_roles WHERE user_id = {$userId}")->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    public function test_tenant_nao_altera_perfil_do_sistema(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $system = $this->createRole(null, 'teste-sistema');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/perfis/{$system}", ['name' => 'Hackeado', '_method' => 'PUT']);

        $this->assertSame('teste-sistema', $this->roleName($system));
    }

    public function test_tela_de_edicao_de_perfil_do_sistema_devolve_para_a_visualizacao(): void
    {
        $user   = $this->createUser($this->createAgency());
        $system = $this->createRole(null, 'teste-sistema');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->assertSame(302, $this->get("/perfis/{$system}/editar")->getStatus());
    }

    public function test_agencia_nao_altera_perfil_de_outra_agencia(): void
    {
        $user  = $this->createUser($this->createAgency('A'));
        $other = $this->createRole($this->createAgency('B'), 'teste-da-b');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/perfis/{$other}", ['name' => 'Hackeado', '_method' => 'PUT']);

        $this->assertSame('teste-da-b', $this->roleName($other));
    }

    public function test_agencia_altera_o_proprio_perfil_dentro_do_que_tem(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $own    = $this->createRole($agency, 'teste-proprio');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/perfis/{$own}", ['name' => 'Renomeado', '_method' => 'PUT', 'permissions' => ['users.view']]);

        $this->assertSame('Renomeado', $this->roleName($own));
    }

    public function test_nao_adiciona_ao_perfil_permissao_que_nao_tem(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $this->createPermission('teste.poder');
        $own = $this->createRole($agency, 'teste-proprio');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/perfis/{$own}", ['name' => 'X', '_method' => 'PUT', 'permissions' => ['teste.poder']]);

        $count = $this->pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id = {$own}")->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function test_nao_se_promove_atribuindo_perfil_com_mais_poder(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $power  = $this->createRole(null, 'teste-poderoso', [$this->createPermission('teste.poder')]);
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/usuarios/{$user['id']}", ['name' => 'X', '_method' => 'PUT', 'role_id' => $power]);

        $this->assertNotContains($power, $this->rolesOf($user['id']));
    }

    public function test_nao_atribui_perfil_de_outra_agencia(): void
    {
        $agency = $this->createAgency('A');
        $user   = $this->createUser($agency);
        $target = $this->createUser($agency, 'alvo@test.com');
        $other  = $this->createRole($this->createAgency('B'), 'teste-da-b');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/usuarios/{$target['id']}", ['name' => 'X', '_method' => 'PUT', 'role_id' => $other]);

        $this->assertNotContains($other, $this->rolesOf($target['id']));
    }

    public function test_formulario_de_usuario_troca_o_perfil_de_verdade(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $target = $this->createUser($agency, 'alvo@test.com');
        $role   = $this->createRole($agency, 'teste-proprio');
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/usuarios/{$target['id']}", ['name' => 'X', '_method' => 'PUT', 'role_id' => $role]);

        $this->assertSame([$role], $this->rolesOf($target['id']));
    }

    public function test_nao_troca_a_senha_de_quem_tem_mais_permissoes(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $boss   = $this->createUser($agency, 'chefe@test.com');
        $power  = $this->createRole(null, 'teste-poderoso', [$this->createPermission('teste.poder')]);
        $this->pdo->exec("INSERT INTO user_roles (user_id, role_id, created_at) VALUES ({$boss['id']}, {$power}, NOW())");
        $before = $this->pdo->query("SELECT password_hash FROM users WHERE id = {$boss['id']}")->fetchColumn();
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/usuarios/{$boss['id']}", ['name' => 'X', '_method' => 'PUT', 'password' => 'senha-do-invasor-1']);

        $after = $this->pdo->query("SELECT password_hash FROM users WHERE id = {$boss['id']}")->fetchColumn();
        $this->assertSame($before, $after);
    }

    public function test_nao_exclui_quem_tem_mais_permissoes(): void
    {
        $agency = $this->createAgency();
        $user   = $this->createUser($agency);
        $boss   = $this->createUser($agency, 'chefe@test.com');
        $power  = $this->createRole(null, 'teste-poderoso', [$this->createPermission('teste.poder')]);
        $this->pdo->exec("INSERT INTO user_roles (user_id, role_id, created_at) VALUES ({$boss['id']}, {$power}, NOW())");
        $this->actingAs($user['id'], self::ADMIN_PERMS);

        $this->post("/usuarios/{$boss['id']}", ['_method' => 'DELETE']);

        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE id = {$boss['id']}")->fetchColumn());
    }

    public function test_perfil_de_outra_agencia_nao_da_permissao_no_login(): void
    {
        $user  = $this->createUser($this->createAgency('A'));
        $other = $this->createRole($this->createAgency('B'), 'teste-da-b', [$this->createPermission('teste.poder')]);
        $this->pdo->exec("INSERT INTO user_roles (user_id, role_id, created_at) VALUES ({$user['id']}, {$other}, NOW())");

        $this->assertNotContains('teste.poder', (new UserRepository())->loadPermissions($user['id']));
    }
}
