<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * BUG-06 — o clique na notificação não pode ficar preso no POST de "lida".
 *
 * A primeira correção do BUG-06 trocou o `@click="markRead(n.id)"` (fogo e
 * esquece, com o `<a>` navegando sozinho) por um `activate()` que dava
 * `preventDefault()` e só navegava se o POST voltasse 200. O efeito colateral
 * era pior que o bug original:
 *
 *   - sessão expirada → 401 `{"error":"Unauthenticated"}`: o clique não levava
 *     a lugar nenhum e a gaveta mostrava o texto cru do middleware, sem
 *     tradução e sem levar ninguém ao login (na navegação, o AuthMiddleware
 *     redirecionaria para /login);
 *   - rede ruim → o `api` espera 30 s (`AbortSignal.timeout`) e não existe
 *     estado de "enviando" no item: o toque ficava meio minuto sem reação
 *     nenhuma e terminava em "Não foi possível atualizar as notificações.";
 *   - qualquer 500 na rota de leitura cancelava a navegação também.
 *
 * Agora o POST vai com `keepalive` (sobrevive à navegação — era esse
 * cancelamento o motivo de esperar) e o `<a>` navega na hora.
 *
 * O JS do sino mora dentro de `layouts/app.php`, então aqui ele é extraído da
 * view e executado **de verdade** no node, com `api`/`window` dublados: estes
 * casos falham na implementação anterior, não num padrão de texto.
 */
class NotificationBellClickTest extends TestCase
{
    /** Dublê de `api` cujo POST fica pendurado até `liberarPost()`. */
    private const API_PENDURADA = <<<'JS'
        class ApiError extends Error {
          constructor(message, status) { super(message); this.name = 'ApiError'; this.status = status; }
          get isNetwork() { return this.status === 0; }
        }

        const posts = [];
        let liberarPost = null;

        globalThis.api = {
          get: async () => ({ count: 2, notifications: [] }),
          post: async (url, body, options) => {
            posts.push({ url, options: options ?? null });
            await new Promise((resolve) => { liberarPost = resolve; });
            throw new ApiError('Falha de conexão. Verifique sua internet e tente de novo.', 0, null);
          },
        };
        JS;

    // ── O clique ─────────────────────────────────────────────────────────────

    public function test_clique_na_notificacao_abre_o_link_sem_esperar_o_post(): void
    {
        $resultado = $this->rodarSino(self::API_PENDURADA, <<<'JS'
            const bell = notifBell('erro de sincronia');
            bell.count = 2;
            bell.notifications = [{ id: 7, action_url: '/aprovacoes/7', title: 'Plano da Padaria' }];

            let preventDefault = 0;
            bell.activate(
              { id: 7, action_url: '/aprovacoes/7' },
              { preventDefault: () => { preventDefault += 1; } }
            );

            // Retrato de enquanto o POST está pendurado: é aqui que o toque
            // ficava morto por até 30 s.
            saida.enquantoPendurado = { preventDefault, href: window.location.href };
            saida.posts = posts;

            liberarPost();
            await esperar();
            saida.depoisDaFalha = { href: window.location.href, error: bell.error };
            JS);

        $this->assertSame(
            ['/notifications/7/read'],
            array_column($resultado['posts'], 'url'),
            'O clique deixou de disparar a marcação como lida.'
        );

        $pendurado = $resultado['enquantoPendurado'];
        $navegouNaHora = $pendurado['preventDefault'] === 0            // o <a> navega sozinho
            || $pendurado['href'] === '/aprovacoes/7';                 // ou o JS já navegou

        $this->assertTrue(
            $navegouNaHora,
            "O clique na notificação esperou a resposta do POST para navegar.\n"
            . "Com rede ruim são 30 s de gaveta aberta sem reação nenhuma, e se o POST falhar "
            . "o link nunca abre.\nEstado com o POST pendurado: " . json_encode($pendurado)
        );
    }

    public function test_marcar_como_lida_vai_com_keepalive_para_sobreviver_a_navegacao(): void
    {
        $resultado = $this->rodarSino(self::API_PENDURADA, <<<'JS'
            const bell = notifBell('erro de sincronia');
            bell.activate({ id: 7, action_url: '/aprovacoes/7' }, { preventDefault: () => {} });
            saida.posts = posts;
            liberarPost();
            await esperar();
            JS);

        $this->assertTrue(
            ($resultado['posts'][0]['options']['keepalive'] ?? false) === true,
            'Sem `keepalive`, a navegação do mesmo clique cancela o POST e a notificação '
            . 'volta a aparecer no poll — foi por isso que a versão anterior esperava a resposta.'
        );
    }

    public function test_api_repassa_o_keepalive_para_o_fetch(): void
    {
        $dubles = <<<'JS'
            globalThis.window = {};
            globalThis.document = { querySelector: () => ({ content: 'token-de-teste' }) };

            let init = null;
            globalThis.fetch = async (url, recebido) => {
              init = recebido;
              return {
                ok: true,
                status: 200,
                headers: { get: () => 'application/json' },
                json: async () => ({ success: true }),
              };
            };
            JS;

        $cenario = <<<'JS'
            (async () => {
              await api.post('/notifications/7/read', null, { keepalive: true });
              const comOpcao = init;

              await api.post('/notifications/mark-all-read');
              const semOpcao = init;

              console.log(JSON.stringify({
                comKeepalive: comOpcao.keepalive === true,
                metodo: comOpcao.method,
                semKeepalive: semOpcao.keepalive ?? null,
              }));
            })();
            JS;

        $resultado = $this->rodarNoNode($dubles . "\n" . $this->fonteDaApi() . "\n" . $cenario);

        $this->assertTrue($resultado['comKeepalive'], 'O `api` ignorou a opção `keepalive`: o POST do sino continua morrendo na navegação.');
        $this->assertSame('POST', $resultado['metodo']);
        $this->assertNull($resultado['semKeepalive'], 'Requisição sem a opção não deve ganhar keepalive (o limite de 64 KB de corpo valeria para upload).');
    }

    // ── Sessão expirada ──────────────────────────────────────────────────────

    public function test_sessao_expirada_leva_ao_login_em_vez_do_erro_cru_do_middleware(): void
    {
        $resultado = $this->rodarSino($this->apiQueRecusa(401, 'Unauthenticated'), <<<'JS'
            const bell = notifBell('erro de sincronia');
            bell.count = 1;
            bell.notifications = [{ id: 9, action_url: null, title: 'Aviso' }];

            // Notificação sem destino: nada navega, então o 401 só se resolve aqui.
            bell.activate({ id: 9, action_url: null }, { preventDefault: () => {} });
            await esperar();

            saida.href  = window.location.href;
            saida.error = bell.error;
            JS);

        $this->assertSame('/login', $resultado['href'], 'O 401 não levou ao login: a gaveta fica mostrando erro e o usuário não tem saída.');
        $this->assertSame('', $resultado['error'], 'O texto cru do middleware ("Unauthenticated") foi mostrado ao usuário, sem tradução.');
    }

    public function test_erro_do_servidor_continua_aparecendo_na_gaveta(): void
    {
        $resultado = $this->rodarSino($this->apiQueRecusa(500, 'Não foi possível concluir (erro 500).'), <<<'JS'
            const bell = notifBell('erro de sincronia');
            bell.count = 1;
            bell.notifications = [{ id: 9, action_url: null, title: 'Aviso' }];

            bell.activate({ id: 9, action_url: null }, { preventDefault: () => {} });
            await esperar();

            saida.href   = window.location.href;
            saida.error  = bell.error;
            saida.count  = bell.count;
            saida.quantas = bell.notifications.length;
            JS);

        $this->assertSame('Não foi possível concluir (erro 500).', $resultado['error'], 'O erro voltou a ser engolido (era o `catch {}` vazio do BUG-06).');
        $this->assertSame('/painel', $resultado['href'], 'Erro de servidor não é sessão expirada: não deslogue o usuário.');
        $this->assertSame(1, $resultado['count'], 'O contador desceu sem o servidor confirmar — ele volta no poll.');
        $this->assertSame(1, $resultado['quantas'], 'A notificação saiu da lista sem ter sido gravada como lida.');
    }

    // ── Harness ──────────────────────────────────────────────────────────────

    /** Dublê de `api` que recusa o POST na hora, com status. */
    private function apiQueRecusa(int $status, string $mensagem): string
    {
        return <<<JS
            class ApiError extends Error {
              constructor(message, status) { super(message); this.name = 'ApiError'; this.status = status; }
            }

            const posts = [];
            const liberarPost = () => {};

            globalThis.api = {
              get: async () => ({ count: 1, notifications: [] }),
              post: async (url, body, options) => {
                posts.push({ url, options: options ?? null });
                throw new ApiError({$this->js($mensagem)}, {$status});
              },
            };
            JS;
    }

    /**
     * Roda o `notifBell()` extraído da view com os dublês e o cenário dados.
     *
     * @return array<string,mixed> o objeto `saida` montado pelo cenário
     */
    private function rodarSino(string $dubles, string $cenario): array
    {
        $codigo = <<<JS
            globalThis.window = { location: { href: '/painel' } };
            globalThis.document = { querySelector: () => ({ content: 'token-de-teste' }) };
            const esperar = () => new Promise((resolve) => setTimeout(resolve, 10));

            {$dubles}

            {$this->fonteDoSino()}

            (async () => {
              const saida = {};
              {$cenario}
              console.log(JSON.stringify(saida));
            })();
            JS;

        return $this->rodarNoNode($codigo);
    }

    /** @return array<string,mixed> */
    private function rodarNoNode(string $codigo): array
    {
        $node = $this->node();

        $processo = proc_open(
            $node,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        $this->assertIsResource($processo, 'Não foi possível executar o node.');

        fwrite($pipes[0], $codigo);
        fclose($pipes[0]);

        $saida = (string) stream_get_contents($pipes[1]);
        $erro  = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($processo);

        $this->assertSame(0, $status, "O JS do sino estourou no node:\n{$erro}");

        $linhas = array_values(array_filter(array_map('trim', explode("\n", $saida)), fn (string $l) => $l !== ''));
        $this->assertNotSame([], $linhas, "O cenário não imprimiu nada.\nstderr:\n{$erro}");

        $decodificado = json_decode((string) end($linhas), true);
        $this->assertIsArray($decodificado, "Saída do node não é JSON:\n{$saida}\n{$erro}");

        return $decodificado;
    }

    /** O bloco `<script>` da view onde vive o sino — sem PHP dentro. */
    private function fonteDoSino(): string
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/layouts/app.php');

        preg_match_all('/<script>(.*?)<\/script>/s', $view, $m);

        $blocos = array_values(array_filter($m[1], fn (string $js) => str_contains($js, 'function notifBell')));

        $this->assertCount(1, $blocos, 'Não achei (ou achei mais de um) bloco <script> com o notifBell() em layouts/app.php.');
        $this->assertStringNotContainsString('<?', $blocos[0], 'O bloco do sino ganhou PHP dentro — este teste precisa de JS puro.');

        return $blocos[0];
    }

    private function fonteDaApi(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/public/js/api.js');
    }

    private function js(string $valor): string
    {
        return (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function node(): string
    {
        $saida = [];
        $status = 0;
        exec('command -v node 2>/dev/null', $saida, $status);

        if ($status !== 0 || !isset($saida[0]) || $saida[0] === '') {
            self::markTestSkipped('node não está no PATH — este caso executa o JS do sino de verdade.');
        }

        return $saida[0];
    }
}
