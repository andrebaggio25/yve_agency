<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Toda tela sem parâmetro abre sem erro e sem aviso do PHP, para quem tem todas
 * as permissões.
 *
 * Nasceu de uma varredura manual: `/contratos/novo` usava variáveis nunca definidas
 * e o botão de salvar nunca funcionou. Em produção o aviso fica escondido
 * (`display_errors=0`), então nenhum teste via nada de errado.
 */
final class AllPagesRenderTest extends FeatureTestCase
{
    /** Chamam serviço externo ou não são telas: fora, para o teste nunca sair para a rede. */
    private const SKIP = '#^/(queue|api|webhook|webhooks|admin|logout|sair)\b|callback|oauth|connect|recomendacoes|/gerar#';

    /** @return list<string> */
    private function staticGetRoutes(): array
    {
        preg_match_all("/\\\$router->get\\('([^']+)'/", (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php'), $m);

        return array_values(array_filter(
            array_unique($m[1]),
            fn (string $path) => !str_contains($path, '{') && !preg_match(self::SKIP, $path),
        ));
    }

    private function actAsFullUser(int $agencyId): void
    {
        $user = $this->createUser($agencyId);
        $this->actingAs($user['id'], array_keys(require dirname(__DIR__, 2) . '/config/permissions.php'));
    }

    public function test_toda_tela_abre_sem_erro_e_sem_aviso_do_php(): void
    {
        $agencyId = $this->createAgency();
        $this->createClient($agencyId);
        $this->actAsFullUser($agencyId);

        $routes = $this->staticGetRoutes();
        $this->assertGreaterThan(50, count($routes), 'A leitura das rotas parou de achar as telas.');

        $failures = [];
        $rendered = 0;
        foreach ($routes as $path) {
            $level = ob_get_level();
            set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$failures, $path): bool {
                $failures[] = "{$path}: {$message} em " . basename($file) . ":{$line}";
                return true;
            });

            try {
                $status = $this->get($path)->getStatus();
                $rendered += $status === 200 ? 1 : 0;
                if ($status >= 500) {
                    $failures[] = "{$path}: HTTP {$status}";
                }
                if (ob_get_level() !== $level) {
                    $failures[] = "{$path}: buffer de saída aberto";
                }
            } catch (\Throwable $e) {
                $failures[] = "{$path}: " . $e->getMessage();
            } finally {
                restore_error_handler();
            }
        }

        $this->assertSame([], $failures);
        // Sem isto, um redirect geral para o login passaria sem renderizar tela nenhuma.
        $this->assertGreaterThan(50, $rendered);
    }

    public function test_formulario_de_novo_contrato_aponta_para_a_rota_certa_e_salva(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $this->actAsFullUser($agencyId);

        $form = $this->get('/contratos/novo')->getBody();
        $this->assertStringContainsString('action="/contratos"', $form);
        $this->assertStringNotContainsString('name="_method"', $form);

        $response = $this->post('/contratos', [
            'client_id' => $client['id'], 'title' => 'Contrato novo', 'value' => '1500,00',
            'currency_code' => 'BRL', 'status' => 'active', 'start_date' => date('Y-m-d'),
        ]);

        $this->assertSame(302, $response->getStatus());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM contracts WHERE title = 'Contrato novo' AND agency_id = {$agencyId}")->fetchColumn());
    }

    public function test_fatura_e_contrato_inexistentes_dao_404_e_nao_500(): void
    {
        $this->actAsFullUser($this->createAgency());

        $this->assertSame(404, $this->get('/faturas/999999')->getStatus());
        $this->assertSame(404, $this->get('/faturas/999999/editar')->getStatus());
        $this->assertSame(404, $this->get('/contratos/999999')->getStatus());
    }
}
