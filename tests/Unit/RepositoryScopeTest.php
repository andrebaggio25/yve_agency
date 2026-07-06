<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Repository;
use App\Support\Auth;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * QA-02 (multi-tenancy): o escopo automático por agency_id é a defesa que impede
 * uma agência de ler dados de outra. Este teste trava o comportamento do
 * Repository::agencyScope()/bindAgency() sem depender de um banco real.
 */
class RepositoryScopeTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function repo(): object
    {
        // Subclasse anônima que expõe os membros protegidos para o teste.
        return new class extends Repository {
            protected string $table = 'dummy';
            public function scope(): string { return $this->agencyScope(); }
            /** @return array<string,mixed> */
            public function bound(): array { $p = []; $this->bindAgency($p); return $p; }
        };
    }

    public function test_scope_filters_by_agency_for_tenant_user(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 7, 'name' => 'X'], [], []);
        $repo = $this->repo();

        $this->assertSame('agency_id = :__agency_id', $repo->scope());
        $this->assertSame([':__agency_id' => 7], $repo->bound());
    }

    public function test_scope_is_unrestricted_for_platform_admin(): void
    {
        Auth::login(['id' => 1, 'agency_id' => null, 'name' => 'Root', 'is_platform_admin' => true], [], []);
        $repo = $this->repo();

        $this->assertSame('1=1', $repo->scope());
        $this->assertSame([], $repo->bound()); // sem agência para vincular
    }

    public function test_scope_throws_without_agency_and_not_platform_admin(): void
    {
        // Nenhuma sessão de agência e não é platform admin → deve falhar fechado,
        // nunca devolver uma query sem filtro de tenant.
        $repo = $this->repo();

        $this->expectException(RuntimeException::class);
        $repo->scope();
    }
}
