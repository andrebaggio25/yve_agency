<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middlewares\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * SEC-06/SEC-08: toda rota que muda estado valida CSRF.
 *
 * O portal era a lacuna: `itemFeedback` e os endpoints de Drive mutavam dados
 * só com o capability-token da URL. Como o token viaja na URL (é compartilhado
 * por e-mail/WhatsApp), qualquer página hostil podia forjar um POST e aprovar
 * um plano ou apagar arquivos em nome do cliente.
 *
 * Isenções legítimas (têm autenticação própria, não de cookie):
 *   - /webhook/*  → HMAC (ClickUp) ou token+instância (Evolution)
 *   - /queue/*    → QUEUE_SECRET comparado com hash_equals
 */
class MutationRoutesHaveCsrfTest extends TestCase
{
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Rotas que se autenticam sem cookie de sessão — CSRF não se aplica. */
    private const EXEMPT_PREFIXES = ['/webhook/', '/queue/'];

    public function test_every_state_changing_route_validates_csrf(): void
    {
        $unprotected = [];

        foreach ($this->collectRoutes() as [$method, $uri, $middlewares]) {
            if (!in_array($method, self::MUTATION_METHODS, true) && $method !== 'ANY') {
                continue;
            }
            if ($this->isExempt($uri)) {
                continue;
            }
            if (!in_array(CsrfMiddleware::class, $middlewares, true)) {
                $unprotected[] = "{$method} {$uri}";
            }
        }

        $this->assertSame(
            [],
            $unprotected,
            "Rota que muda estado sem CsrfMiddleware:\n" . implode("\n", $unprotected)
        );
    }

    /** As mutações do portal (o gap do SEC-08) estão cobertas de fato. */
    public function test_portal_mutations_are_covered(): void
    {
        $portalMutations = array_filter(
            $this->collectRoutes(),
            fn (array $r): bool => str_starts_with($r[1], '/portal/')
                && in_array($r[0], self::MUTATION_METHODS, true)
        );

        $this->assertGreaterThanOrEqual(8, count($portalMutations), 'Esperava as mutações do portal (feedback, aprovação, drive).');

        foreach ($portalMutations as [$method, $uri, $middlewares]) {
            $this->assertContains(CsrfMiddleware::class, $middlewares, "{$method} {$uri} sem CSRF");
        }
    }

    private function isExempt(string $uri): bool
    {
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array{0:string,1:string,2:list<string>}> */
    private function collectRoutes(): array
    {
        $router = new class {
            /** @var list<array{0:string,1:string,2:list<string>}> */
            public array $collected = [];
            /** @var list<string> */
            private array $groupMiddlewares = [];

            private function add(string $m, string $uri, array $mw): void
            {
                $this->collected[] = [$m, $uri, array_merge($this->groupMiddlewares, $mw)];
            }

            public function get(string $u, array $h, array $m = []): void    { $this->add('GET', $u, $m); }
            public function post(string $u, array $h, array $m = []): void   { $this->add('POST', $u, $m); }
            public function put(string $u, array $h, array $m = []): void    { $this->add('PUT', $u, $m); }
            public function patch(string $u, array $h, array $m = []): void  { $this->add('PATCH', $u, $m); }
            public function delete(string $u, array $h, array $m = []): void { $this->add('DELETE', $u, $m); }
            public function any(string $u, array $h, array $m = []): void    { $this->add('ANY', $u, $m); }

            public function group(array $middlewares, \Closure $callback): void
            {
                $previous               = $this->groupMiddlewares;
                $this->groupMiddlewares = array_merge($previous, $middlewares);
                $callback($this);
                $this->groupMiddlewares = $previous;
            }
        };

        $root = dirname(__DIR__, 2);
        require $root . '/routes/web.php';
        require $root . '/routes/api.php';

        return $router->collected;
    }
}
