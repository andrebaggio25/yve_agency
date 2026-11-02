<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Toda view citada no código existe no disco.
 *
 * `View::include()` lança `RuntimeException("View [x] not found")` — ou seja, o
 * nome errado só aparece quando alguém abre a tela, e em produção vira 500 mudo.
 * O `RoutesResolveTest` não pega isso: ele prova que o método do controller
 * existe, não que o `view('modulo.tela')` de dentro dele aponta para um arquivo.
 * Foi assim que duas views inexistentes chegaram à main.
 *
 * Varre três referências literais:
 *   - `view('x.y')` / `Response::view('x.y')` / `View::render()` / `fromView()` em `app/`
 *   - `view_partial('nome')` nas views → `partials/nome.php`
 *   - `view_layout('nome')` nas views → `layouts/nome.php`
 *
 * Nome montado em variável fica fora do alcance deste teste (hoje não existe
 * nenhum: `PdfService::fromView()` recebe literal de quem chama).
 */
class ViewsResolveTest extends TestCase
{
    private const VIEWS_DIR = '/resources/views/';

    public function test_toda_view_citada_no_codigo_existe(): void
    {
        $refs    = $this->collectViewRefs();
        $faltando = [];

        foreach ($refs as [$file, $line, $view, $path]) {
            if (!is_file($path)) {
                $faltando[] = "{$file}:{$line} → view('{$view}') não existe em " . self::VIEWS_DIR;
            }
        }

        $this->assertSame([], $faltando, "Views citadas que não existem:\n" . implode("\n", $faltando));
    }

    public function test_todo_partial_e_layout_citado_nas_views_existe(): void
    {
        $faltando = [];

        foreach ($this->phpFiles($this->root() . self::VIEWS_DIR) as $file) {
            $code = (string) file_get_contents($file);
            $rel  = $this->relative($file);

            foreach ([['view_partial', 'partials'], ['view_layout', 'layouts']] as [$fn, $dir]) {
                preg_match_all('/' . $fn . "\(\s*'([a-z0-9_\-]+)'/i", $code, $m, PREG_OFFSET_CAPTURE);

                foreach ($m[1] as $match) {
                    [$name, $offset] = $match;
                    $path = $this->root() . self::VIEWS_DIR . $dir . '/' . $name . '.php';

                    if (!is_file($path)) {
                        $line       = substr_count(substr($code, 0, (int) $offset), "\n") + 1;
                        $faltando[] = "{$rel}:{$line} → {$fn}('{$name}') não existe em {$dir}/";
                    }
                }
            }
        }

        $this->assertSame([], $faltando, "Partial/layout citado e inexistente:\n" . implode("\n", $faltando));
    }

    public function test_a_varredura_realmente_encontrou_views(): void
    {
        // Guarda do próprio teste: se a regex parar de casar, os testes acima
        // passariam vazios e ninguém veria.
        $this->assertGreaterThan(80, count($this->collectViewRefs()));
    }

    /**
     * @return list<array{0:string,1:int,2:string,3:string}> arquivo, linha, view, caminho esperado
     */
    private function collectViewRefs(): array
    {
        $refs = [];

        foreach ($this->phpFiles($this->root() . '/app') as $file) {
            $code = (string) file_get_contents($file);
            $rel  = $this->relative($file);

            // `->view('x')`, `Response::view('x')`, `View::render('x')`, `fromView('x')`.
            preg_match_all(
                '/(?:->view|::view|::render|->fromView)\(\s*\'([a-z0-9_.\-]+)\'/i',
                $code,
                $m,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($m[1] as $match) {
                [$view, $offset] = $match;
                $refs[] = [
                    $rel,
                    substr_count(substr($code, 0, (int) $offset), "\n") + 1,
                    $view,
                    $this->root() . self::VIEWS_DIR . str_replace('.', '/', $view) . '.php',
                ];
            }
        }

        return $refs;
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            if ($info->isFile() && $info->getExtension() === 'php') {
                $files[] = $info->getPathname();
            }
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace($this->root(), '', $path), '/');
    }
}
