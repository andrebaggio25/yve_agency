<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Auth::forgetClientAgencies();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Auth::forgetClientAgencies();
    }

    // ── check / guest ─────────────────────────────────────────────────────────

    public function test_guest_when_not_logged_in(): void
    {
        $this->assertFalse(Auth::check());
        $this->assertTrue(Auth::guest());
    }

    public function test_check_after_login(): void
    {
        Auth::login(
            ['id' => 1, 'agency_id' => 1, 'name' => 'Test'],
            ['dashboard.view', 'clients.view'],
            [10, 20],
        );

        $this->assertTrue(Auth::check());
        $this->assertFalse(Auth::guest());
    }

    // ── user / id / agencyId ──────────────────────────────────────────────────

    public function test_user_returns_session_data(): void
    {
        Auth::login(['id' => 5, 'agency_id' => 3, 'name' => 'Ana'], [], []);

        $this->assertEquals(5, Auth::id());
        $this->assertEquals(3, Auth::agencyId());
        $this->assertEquals('Ana', Auth::user()['name']);
    }

    public function test_user_returns_null_when_not_logged_in(): void
    {
        $this->assertNull(Auth::user());
        $this->assertNull(Auth::id());
        $this->assertNull(Auth::agencyId());
    }

    // ── permissions ───────────────────────────────────────────────────────────

    public function test_can_returns_true_for_granted_permission(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view', 'content.edit'], []);

        $this->assertTrue(Auth::can('clients.view'));
        $this->assertTrue(Auth::can('content.edit'));
    }

    public function test_can_returns_false_for_missing_permission(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view'], []);

        $this->assertFalse(Auth::can('clients.delete'));
        $this->assertFalse(Auth::can('invoices.view'));
    }

    public function test_cannot_is_inverse_of_can(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view'], []);

        $this->assertFalse(Auth::cannot('clients.view'));
        $this->assertTrue(Auth::cannot('clients.delete'));
    }

    public function test_can_any_returns_true_if_at_least_one_matches(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view'], []);

        $this->assertTrue(Auth::canAny('invoices.view', 'clients.view'));
        $this->assertFalse(Auth::canAny('invoices.view', 'contracts.view'));
    }

    public function test_can_all_requires_all_permissions(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view', 'content.view'], []);

        $this->assertTrue(Auth::canAll('clients.view', 'content.view'));
        $this->assertFalse(Auth::canAll('clients.view', 'invoices.view'));
    }

    // ── client access ─────────────────────────────────────────────────────────

    /**
     * O mapa cliente→agência vem do banco; aqui o pré-carregamos para o teste
     * ficar unitário (é o mesmo caminho que a listagem usa para não virar N+1).
     */
    private function primeClients(): void
    {
        Auth::primeClientAgencies([
            ['id' => 10, 'agency_id' => 1],
            ['id' => 20, 'agency_id' => 1],
            ['id' => 30, 'agency_id' => 1],
            ['id' => 99, 'agency_id' => 1],
            // Cliente de outra agência.
            ['id' => 777, 'agency_id' => 2],
        ]);
    }

    public function test_can_access_client_in_list(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [10, 20, 30]);
        $this->primeClients();

        $this->assertTrue(Auth::canAccessClient(10));
        $this->assertTrue(Auth::canAccessClient(20));
        $this->assertFalse(Auth::canAccessClient(99));
    }

    public function test_clients_view_all_bypasses_client_list(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view_all'], []);
        $this->primeClients();

        $this->assertTrue(Auth::canAccessClient(99));
    }

    /** SEC-11: view_all vale dentro da agência, não fora dela. */
    public function test_view_all_nao_atravessa_a_agencia(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view_all'], []);
        $this->primeClients();

        $this->assertFalse(Auth::canAccessClient(777));
    }

    /** Nem o acesso explícito (sessão antiga, vínculo indevido) atravessa. */
    public function test_acesso_explicito_nao_atravessa_a_agencia(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [777]);
        $this->primeClients();

        $this->assertFalse(Auth::canAccessClient(777));
    }

    /**
     * SEC-11: o recorte da listagem segue a mesma régua do detalhe — `null` é
     * "a agência toda", lista é "só estes". Se divergirem, a lista mostra o que
     * o detalhe nega (ou o contrário).
     */
    public function test_reachable_client_ids_acompanha_o_can_access_client(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [10, 20]);
        $this->assertSame([10, 20], Auth::reachableClientIds());

        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view_all'], []);
        $this->assertNull(Auth::reachableClientIds());

        Auth::login(['id' => 1, 'agency_id' => null, 'name' => 'X', 'is_platform_admin' => true], [], []);
        $this->assertNull(Auth::reachableClientIds());
    }

    public function test_platform_admin_passa_em_qualquer_cliente(): void
    {
        Auth::login(['id' => 1, 'agency_id' => null, 'name' => 'X', 'is_platform_admin' => true], [], []);
        $this->primeClients();

        $this->assertTrue(Auth::canAccessClient(777));
    }

    // ── logout ────────────────────────────────────────────────────────────────

    public function test_logout_clears_session(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view'], [1, 2]);
        $this->assertTrue(Auth::check());

        Auth::logout();

        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
    }
}
