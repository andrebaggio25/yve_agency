<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Módulo de componente Alpine NUNCA pode ser carregado com `defer`.
 *
 * Bug real (FE-02): ao extrair o JS das views para `public/js/*.js`, os
 * `<script>` saíram com `defer`. Scripts `defer` executam na **ordem do
 * documento** — e o Alpine está no `<head>`. Ou seja: o Alpine executava e
 * chamava `Alpine.start()` **antes** de o módulo definir `driveManager()` /
 * `approvalShow()` / `contentShow()`. Resultado em produção: componentes
 * mortos ("ReferenceError: X is not defined") — não dava para criar pasta no
 * portal nem ver o preview na aprovação.
 *
 * Script clássico (sem `defer`) no `<body>` executa durante o parse, portanto
 * ANTES de qualquer `defer` — que é o que queremos.
 *
 * `public/js/vendor/*` (Alpine, Chart) pode usar `defer`: são bibliotecas, não
 * definem componentes que o Alpine precise achar no start.
 */
class ScriptLoadOrderTest extends TestCase
{
    public function test_component_modules_are_not_deferred(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            $code = (string) file_get_contents($file);

            // <script ... defer ... src="...asset('/js/xxx.js')...>  — exceto vendor
            preg_match_all('/<script[^>]*>/i', $code, $tags);

            foreach ($tags[0] as $tag) {
                if (!str_contains($tag, 'defer')) {
                    continue;
                }
                if (!preg_match("#/js/([^']+\.js)#", $tag, $m)) {
                    continue;
                }
                if (str_starts_with($m[1], 'vendor/')) {
                    continue; // biblioteca: pode (e deve) ser defer
                }

                $rel = str_replace(dirname(__DIR__, 2) . '/', '', $file);
                $offenders[] = "{$rel} → /js/{$m[1]} com defer";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Módulo de componente com defer executa DEPOIS do Alpine.start() e o "
            . "componente morre com ReferenceError. Remova o defer:\n" . implode("\n", $offenders)
        );
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

        return $files;
    }
}
