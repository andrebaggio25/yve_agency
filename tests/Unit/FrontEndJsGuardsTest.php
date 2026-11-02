<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Dois erros de JS que o PHPUnit só pega lendo a view (BUG-06) — no navegador
 * eles não dão exceção nenhuma, só mostram coisa errada na tela:
 *
 *   1. `new Date('2026-10-31 12:00:00')` é **Invalid Date** no Safari/iOS. O
 *      chat da tarefa carimbava cada mensagem com "Invalid Date" no iPhone —
 *      e metade dos clientes/equipe aprova pelo celular. Com o `T` no lugar do
 *      espaço todo navegador lê.
 *   2. `x-init="init()"` num componente cujo `x-data` já tem `init()`: o Alpine
 *      chama o `init()` do objeto sozinho, então o do `x-init` é a **segunda**
 *      chamada. No sino de notificações isso subia dois `setInterval` de 30 s e
 *      deixava o primeiro órfão (o id guardado era só o do segundo).
 *
 * Verificado em Chromium 3.14.1: com os dois, o `init()` roda 2×.
 */
class FrontEndJsGuardsTest extends TestCase
{
    public function test_nenhuma_data_do_banco_vai_crua_para_new_date(): void
    {
        $offenders = [];

        foreach ($this->jsSources() as $file) {
            $code = (string) file_get_contents($file);

            // Pega o argumento até o fim da linha: o que precisa estar lá é um
            // `T` entre a data e a hora — por `replace(' ', 'T')` ou concatenado
            // (`dia + 'T12:00:00'`). A busca é textual de propósito: este padrão
            // é copiado e colado entre as views.
            preg_match_all('/new Date\(([^;\n]*)/', $code, $m);

            foreach ($m[1] as $bruto) {
                $bruto = trim($bruto);
                if ($bruto === '' || str_starts_with($bruto, ')')) {
                    continue;                      // new Date() — agora
                }

                $arg = trim(rtrim($bruto, ')'));

                if ($arg === '' || is_numeric($arg) || str_contains($arg, 'Date.now')) {
                    continue;                      // timestamp
                }
                if (str_contains($arg, "'T") || str_contains($arg, '"T')) {
                    continue;                      // o T está lá
                }

                $offenders[] = $this->relativo($file) . " → new Date({$arg})";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Data do banco ('Y-m-d H:i:s') direto no `new Date()` vira Invalid Date no Safari/iOS.\n"
            . "Use `new Date(String(x).replace(' ', 'T'))`:\n" . implode("\n", $offenders)
        );
    }

    public function test_init_com_efeito_colateral_nao_e_chamado_tambem_pelo_x_init(): void
    {
        $offenders = [];

        foreach ($this->jsSources() as $file) {
            $code = (string) file_get_contents($file);

            if (!preg_match('/x-init\s*=\s*"init\(\)"/', $code)) {
                continue;
            }

            // Só pesa quando o init() dispara timer ou requisição: aí a segunda
            // chamada duplica o poll. init() vazio (ex.: kanbanBoard) é inócuo.
            foreach ($this->componentesComInitEfetivo($code) as $nome) {
                if (preg_match('/x-data\s*=\s*"' . preg_quote($nome, '/') . '\s*\(/', $code)) {
                    $offenders[] = $this->relativo($file) . " → {$nome}() dispara timer/requisição no init() e a view ainda usa x-init=\"init()\"";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "O Alpine já executa o init() do objeto de x-data; com x-init=\"init()\" ele roda duas vezes "
            . "(dois setInterval, um sem ninguém para limpar):\n" . implode("\n", $offenders)
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return list<string> Views + módulos JS do app (sem vendor). */
    private function jsSources(): array
    {
        $js = glob(dirname(__DIR__, 2) . '/public/js/*.js') ?: [];

        return [...$this->viewFiles(), ...$js];
    }

    /**
     * Funções da view cujo objeto tem um `init()` que faz algo observável
     * (timer ou requisição) — é só nesse caso que chamar duas vezes machuca.
     *
     * @return list<string>
     */
    private function componentesComInitEfetivo(string $code): array
    {
        $nomes = [];

        preg_match_all('/function\s+([A-Za-z_$][\w$]*)\s*\([^)]*\)\s*\{(.*?)\n\}/s', $code, $m, PREG_SET_ORDER);
        foreach ($m as [, $nome, $corpo]) {
            if (!preg_match('/\n\s*(async\s+)?init\s*\(\)\s*\{(.*?)\n\s{4}\}/s', $corpo, $init)) {
                continue;
            }
            if (preg_match('/setInterval|setTimeout|fetch\(|api\./', $init[2])) {
                $nomes[] = $nome;
            }
        }

        return $nomes;
    }

    private function relativo(string $file): string
    {
        return str_replace(dirname(__DIR__, 2) . '/', '', $file);
    }

    /** @return list<string> */
    private function viewFiles(): array
    {
        $dir   = dirname(__DIR__, 2) . '/resources/views';
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
