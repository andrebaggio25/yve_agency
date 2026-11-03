<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

/** Controller de teste: falha carregando um dado sensível nos argumentos. */
final class FalhaDeTesteController extends Controller
{
    public function boom(Request $request): Response
    {
        $this->falhar((string) $request->param('token'));
    }

    private function falhar(string $segredo): never
    {
        throw new \RuntimeException('falhou de propósito');
    }
}

/**
 * INFRA-02 e o log do erro 500: conexão persistente morta não pode virar erro na
 * tela do usuário, e o 500 de produção precisa deixar rastro sem vazar segredo.
 */
class ConnectionAndErrorLogTest extends FeatureTestCase
{
    private string $logFile = '';
    private string $previousLog = '';
    private string $previousIgnoreArgs = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile            = (string) tempnam(sys_get_temp_dir(), 'yve_log_');
        $this->previousLog        = (string) ini_get('error_log');
        $this->previousIgnoreArgs = (string) ini_get('zend.exception_ignore_args');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousLog);
        ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
        if ($this->logFile !== '') {
            @unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function test_conexao_persistente_morta_nao_derruba_a_requisicao(): void
    {
        $vitima = (int) Database::connection()->query('SELECT pg_backend_pid()')->fetchColumn();

        $carrasco = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', env('DB_HOST'), env('DB_PORT'), env('DB_NAME')),
            (string) env('DB_USER'),
            (string) env('DB_PASS'),
        );
        $carrasco->query("SELECT pg_terminate_backend({$vitima})");
        unset($carrasco);

        // Nova requisição: o processo recebe de volta a conexão guardada, morta.
        Database::reset();
        $conexao = Database::connection();

        $this->assertSame(1, (int) $conexao->query('SELECT 1')->fetchColumn());
        $this->assertNotSame($vitima, (int) $conexao->query('SELECT pg_backend_pid()')->fetchColumn());

        // O resto da suíte segue numa conexão boa.
        $this->pdo = $conexao;
    }

    public function test_erro_500_vai_para_o_log_sem_token_nem_argumentos(): void
    {
        ini_set('error_log', $this->logFile);
        ini_set('zend.exception_ignore_args', '1');

        $this->router->get('/teste-falha/{token}', [FalhaDeTesteController::class, 'boom']);

        $response = $this->get('/teste-falha/segredo-do-portal');
        $log      = (string) file_get_contents($this->logFile);

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('GET /teste-falha/{token}', $log);
        $this->assertStringContainsString('falhou de propósito', $log);
        $this->assertStringContainsString('ConnectionAndErrorLogTest.php', $log);
        $this->assertStringNotContainsString('segredo-do-portal', $log);
    }

    public function test_a_entrada_da_aplicacao_liga_a_pilha_sem_argumentos(): void
    {
        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        $this->assertStringContainsString("ini_set('zend.exception_ignore_args', '1')", $index);
    }
}
