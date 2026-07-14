<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * FE-01: nenhuma view pode carregar script/CSS de CDN em runtime.
 *
 * O Tailwind por CDN compilava no navegador a cada page load (lento, sem purge)
 * e obrigava a CSP a aceitar `unsafe-eval`. Assets agora são buildados e
 * self-hosted. Este teste impede a volta do atalho — inclusive numa view nova
 * criada às pressas.
 */
class NoRuntimeCdnTest extends TestCase
{
    private const CDN_HOSTS = [
        'cdn.tailwindcss.com',
        'cdn.jsdelivr.net',
        'unpkg.com',
        'cdnjs.cloudflare.com',
    ];

    public function test_no_view_loads_assets_from_a_cdn(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            $code = (string) file_get_contents($file);

            foreach (self::CDN_HOSTS as $host) {
                if (str_contains($code, $host)) {
                    $rel = str_replace(dirname(__DIR__, 2) . '/', '', $file);
                    $offenders[] = "{$rel} → {$host}";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "View carregando asset de CDN (use asset('/css/app.css') ou /js/vendor/* — rode `npm run build`):\n"
            . implode("\n", $offenders)
        );
    }

    /** O CSS buildado precisa estar versionado: o hosting não roda `npm run build`. */
    public function test_built_assets_are_committed(): void
    {
        $public = dirname(__DIR__, 2) . '/public';

        $this->assertFileExists($public . '/css/app.css', 'Rode `npm run build` e versione public/css/app.css.');
        $this->assertFileExists($public . '/js/vendor/alpine.min.js');
        $this->assertFileExists($public . '/js/vendor/chart.umd.min.js');
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
