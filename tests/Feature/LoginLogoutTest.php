<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\LoginThrottle;

/**
 * QA-05 — a porta de entrada do sistema, pela rota de verdade.
 *
 * A suíte já cobria o que o login **recusa** (senha errada, conta inativa,
 * bloqueio por tentativa, troca de senha) via `AuthService`, mas ninguém
 * exercitava `POST /login` e `POST /logout` ponta a ponta: um erro no
 * controller, no CSRF do logout ou no carregamento de permissões passaria
 * verde. Sem o caso positivo, teste de recusa não prova nada — pode estar
 * passando por 404.
 */
final class LoginLogoutTest extends FeatureTestCase
{
    private const SENHA = 'senha-forte-123';

    protected function setUp(): void
    {
        parent::setUp();

        // O POST /login passa pelo RateLimitMiddleware: lixo de outro teste
        // bloquearia este.
        foreach (glob(storage_path('framework/rate_limit_*')) ?: [] as $file) {
            @unlink($file);
        }
    }

    protected function tearDown(): void
    {
        // `Auth::logout()` chama `session_destroy()`; sem religar a sessão, os
        // testes seguintes rodariam com a sessão fechada (outro estado).
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (isset($this->pdo)) {
            $this->pdo->exec("DELETE FROM roles WHERE slug LIKE 'teste-%'");
            $this->pdo->exec("DELETE FROM permissions WHERE slug LIKE 'teste.%'");
        }

        parent::tearDown();
    }

    /** Usuário com um papel de verdade — é dele que saem as permissões da sessão. */
    private function createUserComPapel(int $agencyId, string $permission): array
    {
        $user = $this->createUser($agencyId, 'login@test.com', self::SENHA);

        $permId = $this->pdo->prepare(
            "INSERT INTO permissions (name, slug, module, created_at) VALUES (:n, :s, 'teste', NOW()) RETURNING id"
        );
        $permId->execute([':n' => $permission, ':s' => $permission]);

        $roleId = $this->pdo->prepare(
            "INSERT INTO roles (agency_id, name, slug, created_at, updated_at)
             VALUES (:a, 'Teste Login', 'teste-login', NOW(), NOW()) RETURNING id"
        );
        $roleId->execute([':a' => $agencyId]);

        $role = (int) $roleId->fetchColumn();
        $this->pdo->exec('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (' . $role . ', ' . (int) $permId->fetchColumn() . ', NOW())');
        $this->pdo->exec('INSERT INTO user_roles (user_id, role_id, created_at) VALUES (' . $user['id'] . ', ' . $role . ', NOW())');

        return $user;
    }

    private function logCount(string $action): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE action = :a');
        $stmt->execute([':a' => $action]);

        return (int) $stmt->fetchColumn();
    }

    // ── Login ────────────────────────────────────────────────────────────────

    public function test_senha_certa_abre_a_sessao_com_as_permissoes_do_papel(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUserComPapel($agencyId, 'teste.entrar');
        LoginThrottle::clear($user['email']);

        $response = $this->post('/login', ['email' => $user['email'], 'password' => self::SENHA]);

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/dashboard', $response->getHeaders()['Location'] ?? null);
        $this->assertSame($user['id'], (int) ($_SESSION['user']['id'] ?? 0));
        $this->assertContains('teste.entrar', $_SESSION['permissions'] ?? []);
        // O hash da senha nunca vai para o arquivo de sessão (SEC-18).
        $this->assertArrayNotHasKey('password_hash', $_SESSION['user'] ?? []);
        $this->assertSame(1, $this->logCount('login'), 'Entrada no sistema é ação sensível: tem de ir para activity_logs.');
    }

    public function test_senha_errada_nao_abre_sessao_e_volta_para_o_login(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUserComPapel($agencyId, 'teste.entrar');
        LoginThrottle::clear($user['email']);

        $response = $this->post('/login', ['email' => $user['email'], 'password' => 'senha-errada']);

        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function test_login_sem_csrf_e_recusado(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUserComPapel($agencyId, 'teste.entrar');
        LoginThrottle::clear($user['email']);

        $response = $this->post('/login', ['email' => $user['email'], 'password' => self::SENHA], withCsrf: false);

        $this->assertSame(419, $response->getStatus());
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function test_login_volta_para_a_tela_que_o_usuario_tentou_abrir(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUserComPapel($agencyId, 'teste.entrar');
        LoginThrottle::clear($user['email']);

        // É o que o AuthMiddleware guarda quando um visitante bate numa tela interna.
        $this->get('/clientes');

        $response = $this->post('/login', ['email' => $user['email'], 'password' => self::SENHA]);

        $this->assertSame('/clientes', $response->getHeaders()['Location'] ?? null);
    }

    public function test_quem_ja_esta_logado_nao_ve_o_formulario_de_login(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id']);

        $response = $this->get('/login');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/dashboard', $response->getHeaders()['Location'] ?? null);
    }

    // ── Logout ───────────────────────────────────────────────────────────────

    public function test_logout_derruba_a_sessao_e_registra_no_log(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], ['dashboard.view']);

        $response = $this->post('/logout');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);
        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertSame(1, $this->logCount('logout'));
    }

    public function test_logout_sem_csrf_nao_derruba_a_sessao(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], ['dashboard.view']);

        $response = $this->post('/logout', [], withCsrf: false);

        $this->assertSame(419, $response->getStatus());
        $this->assertSame($user['id'], (int) ($_SESSION['user']['id'] ?? 0), 'CSRF recusado não pode mexer na sessão.');
    }

    public function test_visitante_nao_passa_pelo_logout(): void
    {
        $response = $this->post('/logout');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);
        $this->assertSame(0, $this->logCount('logout'));
    }
}
