<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Support\LoginThrottle;

/**
 * BUG-02 + SEC-18: recuperação de senha e endurecimento do login.
 * O e-mail é falso de propósito: teste nunca fala com SMTP de verdade.
 */
final class PasswordResetTest extends FeatureTestCase
{
    private const NEW_PASSWORD = 'nova-senha-forte-456';

    private int $agencyId;
    /** @var array{id:int,email:string,password:string} */
    private array $user;
    private string $lastResetUrl = '';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (glob(storage_path('framework/rate_limit_*')) ?: [] as $file) {
            @unlink($file);
        }

        $this->agencyId = $this->createAgency();
        $this->user     = $this->createUser($this->agencyId);
        LoginThrottle::clear($this->user['email']);
    }

    private function service(): AuthService
    {
        $test  = $this;
        $email = new class ($test) extends EmailService {
            public function __construct(private readonly PasswordResetTest $test) {}

            public function send(string $to, string $toName, string $template, array $vars = [], string $locale = 'pt'): array
            {
                $this->test->captureResetUrl((string) ($vars['reset_url'] ?? ''));
                return ['success' => true];
            }
        };

        return new AuthService(new UserRepository(), $email);
    }

    public function captureResetUrl(string $url): void
    {
        $this->lastResetUrl = $url;
    }

    private function requestToken(): string
    {
        $this->service()->sendPasswordResetLink($this->user['email']);
        $this->assertNotSame('', $this->lastResetUrl);

        return basename($this->lastResetUrl);
    }

    public function test_telas_de_senha_renderizam_sem_erro(): void
    {
        $this->assertSame(200, $this->get('/esqueci-senha')->getStatus());
        $this->assertSame(200, $this->get('/forgot-password')->getStatus());

        $invalid = $this->get('/redefinir-senha/' . str_repeat('a', 64));
        $this->assertSame(200, $invalid->getStatus());
        $this->assertStringNotContainsString('name="password"', $invalid->getBody());
    }

    public function test_link_valido_mostra_o_formulario(): void
    {
        $response = $this->get('/redefinir-senha/' . $this->requestToken());

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('name="password"', $response->getBody());
    }

    public function test_banco_guarda_so_o_hash_do_token(): void
    {
        $token  = $this->requestToken();
        $stored = $this->pdo->query('SELECT token FROM password_reset_tokens')->fetchColumn();

        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', $token), $stored);
    }

    public function test_fluxo_completo_troca_a_senha_e_o_link_nao_vale_duas_vezes(): void
    {
        $token = $this->requestToken();

        $this->assertTrue($this->service()->resetPassword($token, self::NEW_PASSWORD)['success']);
        $this->assertTrue($this->service()->attempt($this->user['email'], self::NEW_PASSWORD)['success']);
        $this->assertFalse($this->service()->resetPassword($token, 'outra-senha-forte-789')['success']);
    }

    public function test_post_com_email_inexistente_responde_igual_a_existente(): void
    {
        $response = $this->post('/esqueci-senha', ['email' => 'ninguem@test.com']);

        $this->assertSame(302, $response->getStatus());
        $this->assertSame(t('auth.reset_link_sent'), $_SESSION['flash']['success'] ?? null);
    }

    public function test_conta_bloqueia_depois_de_muitas_falhas_mesmo_com_senha_certa(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            $this->service()->attempt($this->user['email'], 'senha-errada');
        }

        $result = $this->service()->attempt($this->user['email'], $this->user['password']);

        $this->assertFalse($result['success']);
        $this->assertGreaterThan(0, LoginThrottle::secondsLocked($this->user['email']));
    }

    public function test_redefinir_a_senha_libera_a_conta_bloqueada(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            LoginThrottle::recordFailure($this->user['email']);
        }

        $this->service()->resetPassword($this->requestToken(), self::NEW_PASSWORD);

        $this->assertSame(0, LoginThrottle::secondsLocked($this->user['email']));
    }

    public function test_email_inexistente_e_senha_errada_dao_a_mesma_mensagem(): void
    {
        $missing = $this->service()->attempt('ninguem@test.com', 'qualquer-coisa');
        $wrong   = $this->service()->attempt($this->user['email'], 'qualquer-coisa');
        LoginThrottle::clear('ninguem@test.com');

        $this->assertSame($missing['message'], $wrong['message']);
    }

    public function test_conta_inativa_so_e_revelada_a_quem_tem_a_senha(): void
    {
        $this->pdo->exec("UPDATE users SET status = 'inactive' WHERE id = {$this->user['id']}");

        $wrong = $this->service()->attempt($this->user['email'], 'senha-errada');
        $right = $this->service()->attempt($this->user['email'], $this->user['password']);

        $this->assertSame(t('auth.invalid_credentials'), $wrong['message']);
        $this->assertSame(t('auth.inactive_user'), $right['message']);
    }

    public function test_sessao_nao_guarda_o_hash_da_senha(): void
    {
        $this->service()->attempt($this->user['email'], $this->user['password']);

        $this->assertArrayNotHasKey('password_hash', $_SESSION['user']);
    }

    public function test_trocar_a_senha_derruba_a_sessao_aberta(): void
    {
        $this->service()->attempt($this->user['email'], $this->user['password']);
        $_SESSION['auth_checked_at'] = 0;
        // Passou da autenticação; o que vier depois (403 sem perfil) não é o assunto aqui.
        $this->assertNotSame(302, $this->get('/dashboard')->getStatus());

        $this->service()->resetPassword($this->requestToken(), self::NEW_PASSWORD);
        $_SESSION['auth_checked_at'] = 0;

        $response = $this->get('/dashboard');
        $this->assertSame(302, $response->getStatus());
    }

    public function test_desativar_o_usuario_derruba_a_sessao_aberta(): void
    {
        $this->service()->attempt($this->user['email'], $this->user['password']);
        $this->pdo->exec("UPDATE users SET status = 'inactive' WHERE id = {$this->user['id']}");
        $_SESSION['auth_checked_at'] = 0;

        $this->assertSame(302, $this->get('/dashboard')->getStatus());
    }
}
