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

    /** SEC-10: com os assets self-hosted (FE-01), script só pode vir de 'self'. */
    public function test_scripts_come_only_from_self_without_eval(): void
    {
        $csp = $this->csp();

        preg_match('/script-src([^;]*)/', $csp, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringNotContainsString('unsafe-eval', $scriptSrc, "'unsafe-eval' voltou — era exigência do Tailwind CDN, que não existe mais.");
        $this->assertStringNotContainsString('cdn.', $scriptSrc, 'Script de CDN voltou — os assets são self-hosted (FE-01).');
        $this->assertStringContainsString("'self'", $scriptSrc);
    }
}
