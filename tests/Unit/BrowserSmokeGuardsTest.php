<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * O gate de navegador (`npm run test:browser`) não pode mentir.
 *
 * O smoke é o único teste que cobre renderização de tela de verdade, e duas
 * formas de ele passar sem provar nada já estavam no código (QA-05):
 *
 *   1. `smoke.mjs` descartava a resposta do `page.goto()`. O `page.goto` **não**
 *      lança em 4xx/5xx, e `errors/403.php`, `404.php` e `500.php` são HTML puro
 *      — sem `<script>`, sem `[x-data]` — então a sonda de Alpine devolvia
 *      `null` e nada entrava em `errors`. O 500/403/404 ainda caía por sorte no
 *      listener de console (o Chromium loga "Failed to load resource: … 500"
 *      para documento principal com erro), mas **redirect não gera erro de
 *      console nenhum**: `/tarefas` respondendo 302 → `/login` imprimia ✅
 *      tendo validado o formulário de login. Verificado contra servidor-dublê.
 *   2. `smoke.sh` subia o `php -S` com `PHP_CLI_SERVER_WORKERS=4` e matava só o
 *      PID do pai: os 4 workers sobreviviam reparentados no PID 1, segurando a
 *      porta. Na rodada seguinte o `php -S` novo morria com "Address already in
 *      use" e o smoke rodava inteiro contra o zumbi da rodada anterior — com o
 *      DB_* e o APP_ENV congelados — devolvendo 8 ✅. Reproduzido: duas rodadas
 *      seguidas, a 2ª atendida pelos mesmos PIDs da 1ª.
 *
 * Este teste é textual de propósito: ele roda sempre, sem banco, sem node e sem
 * navegador. Se um dia o smoke for reescrito, atualize as buscas daqui junto.
 */
class BrowserSmokeGuardsTest extends TestCase
{
    public function test_o_smoke_confere_o_status_http_da_navegacao(): void
    {
        $code = $this->arquivo('tests/browser/smoke.mjs');

        $this->assertMatchesRegularExpression(
            '/=\s*await\s+page\.goto\(/',
            $code,
            'O smoke descarta a resposta do page.goto(): guarde-a para poder olhar o status.'
        );

        $this->assertMatchesRegularExpression(
            '/\.(ok|status)\(\)/',
            $code,
            'O smoke não confere o status HTTP. Tela que responde 500/403/404 é HTML sem '
            . '<script> e sem [x-data]: a sonda de Alpine devolve null e a tela entra como ✅.'
        );
    }

    public function test_o_smoke_confere_a_url_final_da_navegacao(): void
    {
        $code = $this->arquivo('tests/browser/smoke.mjs');

        $this->assertStringContainsString(
            'page.url()',
            $code,
            'O smoke não olha onde a navegação terminou. Redirect não gera erro de console: '
            . '/dashboard respondendo 302 → /login valida o formulário de login como se fosse o dashboard.'
        );

        // Não basta ler a URL: tem de comparar com a que foi pedida.
        $this->assertMatchesRegularExpression(
            '/caminhoFinal\s*!==?\s*caminhoPedido|caminhoPedido\s*!==?\s*caminhoFinal/',
            $code,
            'O smoke lê page.url() mas não compara com o caminho pedido.'
        );
    }

    public function test_o_smoke_sh_mata_o_grupo_de_processos_do_servidor(): void
    {
        $sh = $this->arquivo('tests/browser/smoke.sh');

        // `PHP_CLI_SERVER_WORKERS` forka: matar o pai deixa os filhos na porta.
        $this->assertStringContainsString(
            'PHP_CLI_SERVER_WORKERS',
            $sh,
            'Guarda desatualizada: o smoke não usa mais workers no php -S — revise este teste.'
        );

        $this->assertStringContainsString(
            'set -m',
            $sh,
            'Sem `set -m` o php -S não é líder do próprio grupo e `kill -- -PID` não alcança os workers.'
        );

        $this->assertMatchesRegularExpression(
            '/kill\s+--\s+-"?\$\{?SERVER_PID/',
            $sh,
            'O trap mata só o PID do pai: os 4 workers viram órfãos (ppid 1) e seguram a porta, '
            . 'e a rodada seguinte roda contra eles.'
        );
    }

    public function test_o_smoke_sh_confere_que_o_servidor_que_subiu_esta_vivo(): void
    {
        $sh = $this->arquivo('tests/browser/smoke.sh');

        $this->assertMatchesRegularExpression(
            '/kill\s+-0\s+"?\$\{?SERVER_PID/',
            $sh,
            'O readiness só pergunta "alguém responde?" — e um órfão responde. Confira com '
            . '`kill -0 "$SERVER_PID"` que o servidor que este script subiu está vivo.'
        );

        // Sem timeout, um processo que só segura a porta aceita a conexão e
        // nunca responde: o curl bloqueia para sempre e o `kill -0` acima não
        // roda de novo. Verificado prendendo a 8099 com um socket em listen.
        $this->assertMatchesRegularExpression(
            '/curl[^\n]*--max-time[^\n]*\/login/',
            $sh,
            'O curl de readiness não tem --max-time: contra um processo que aceita a conexão e '
            . 'não responde, o script trava para sempre.'
        );
    }

    private function arquivo(string $relativo): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relativo;
        $this->assertFileExists($path, "O gate de navegador perdeu o arquivo {$relativo}.");

        return (string) file_get_contents($path);
    }
}
