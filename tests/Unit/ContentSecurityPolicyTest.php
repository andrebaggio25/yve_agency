<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Regressão do UP-01: a CSP com `connect-src 'self'` bloqueava silenciosamente
 * os PUTs do upload direto browser→Drive (googleapis.com). Se alguém apertar a
 * CSP de novo sem manter o Google, este teste quebra antes do upload quebrar.
 */
class ContentSecurityPolicyTest extends TestCase
{
    private function csp(): string
    {
        $m = new \ReflectionMethod(Response::class, 'contentSecurityPolicy');
        return (string) $m->invoke(null);
    }

    public function test_connect_src_allows_google_apis_for_direct_upload(): void
    {
        $csp = $this->csp();
        $this->assertMatchesRegularExpression(
            '/connect-src[^;]*https:\/\/www\.googleapis\.com/',
            $csp,
            'connect-src precisa permitir www.googleapis.com — sem isso o upload direto (UP-01) morre no navegador.'
        );
    }

    public function test_keeps_baseline_restrictions(): void
    {
        $csp = $this->csp();
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    /** SEC-10/FE-01: nenhuma origem externa de script — os assets são self-hosted. */
    public function test_scripts_come_only_from_self(): void
    {
        $csp = $this->csp();

        preg_match('/script-src([^;]*)/', $csp, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringNotContainsString('cdn.', $scriptSrc, 'Script de CDN voltou — os assets são self-hosted (FE-01).');
        $this->assertStringNotContainsString('http', $scriptSrc, 'Origem externa de script na CSP — só self é permitido.');
        $this->assertStringContainsString("'self'", $scriptSrc);
    }

    /**
     * `'unsafe-eval'` precisa CONTINUAR na CSP enquanto usarmos o Alpine padrão.
     *
     * Regressão real: ao endurecer a CSP eu removi `unsafe-eval`, e o Alpine —
     * que compila `x-data`/`@click`/`x-text` com `new AsyncFunction()` — parou
     * de executar. Efeito em produção: menus, modais e o upload do portal
     * mortos, com `EvalError` no console. Este teste existe para impedir que
     * alguém "melhore" a CSP de novo sem antes migrar para `@alpinejs/csp`.
     */
    public function test_unsafe_eval_is_present_because_alpine_needs_it(): void
    {
        $this->assertStringContainsString(
            "'unsafe-eval'",
            $this->csp(),
            "Sem 'unsafe-eval' o Alpine.js não avalia expressões e a UI inteira quebra. "
            . 'Só remova junto com a migração para @alpinejs/csp (ver SEC-10 no PLANO_MESTRE).'
        );
    }
}
