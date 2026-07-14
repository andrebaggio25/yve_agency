<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Toda rota registrada aponta para uma classe e um método que existem.
 *
 * Um handler quebrado só aparece quando alguém abre a URL — e o erro é um 500
 * mudo em produção. Este teste pega isso no CI. Motivado pelo ARCH-03 (mover as
 * 10 rotas de Drive do PortalController para o PortalDriveController): renomear
 * ou extrair um controller sem atualizar as rotas passa a quebrar a suíte.
 *
 * Carrega `routes/web.php` e `routes/api.php` com um router-espião: as rotas se
 * registram nele exatamente como no runtime, sem precisar de Container nem banco.
 */
class RoutesResolveTest extends TestCase
{
    public function test_every_route_handler_exists(): void
    {
        $broken = [];

        foreach ($this->collectRoutes() as [$method, $uri, $handler]) {
            [$class, $action] = [$handler[0] ?? null, $handler[1] ?? null];

            if (!is_string($class) || !class_exists($class)) {
                $broken[] = "{$method} {$uri} → classe inexistente: " . var_export($class, true);
                continue;
            }
            if (!is_string($action) || !method_exists($class, $action)) {
                $broken[] = "{$method} {$uri} → {$class}::" . var_export($action, true) . '() não existe';
            }
        }

        $this->assertSame([], $broken, "Rotas apontando para handler inexistente:\n" . implode("\n", $broken));
    }

    public function test_the_route_files_were_actually_loaded(): void
    {
        // Guarda do próprio teste: se o require falhar em silêncio, o teste
        // acima passaria vazio e ninguém veria.
        $this->assertGreaterThan(100, count($this->collectRoutes()));
    }

    /** @return list<array{0:string,1:string,2:array}> */
    private function collectRoutes(): array
    {
        $router = new class {
            /** @var list<array{0:string,1:string,2:array}> */
            public array $collected = [];

            public function get(string $uri, array $h, array $m = []): void    { $this->collected[] = ['GET', $uri, $h]; }
            public function post(string $uri, array $h, array $m = []): void   { $this->collected[] = ['POST', $uri, $h]; }
            public function put(string $uri, array $h, array $m = []): void    { $this->collected[] = ['PUT', $uri, $h]; }
            public function patch(string $uri, array $h, array $m = []): void  { $this->collected[] = ['PATCH', $uri, $h]; }
            public function delete(string $uri, array $h, array $m = []): void { $this->collected[] = ['DELETE', $uri, $h]; }
            public function any(string $uri, array $h, array $m = []): void    { $this->collected[] = ['ANY', $uri, $h]; }

            public function group(array $middlewares, \Closure $callback): void
            {
                $callback($this);
            }
        };

        $root = dirname(__DIR__, 2);
        require $root . '/routes/web.php';
        require $root . '/routes/api.php';

        return $router->collected;
    }
}
