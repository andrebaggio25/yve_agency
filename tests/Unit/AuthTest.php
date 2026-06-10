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
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
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

    public function test_can_access_client_in_list(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [10, 20, 30]);

        $this->assertTrue(Auth::canAccessClient(10));
        $this->assertTrue(Auth::canAccessClient(20));
        $this->assertFalse(Auth::canAccessClient(99));
    }

    public function test_clients_view_all_bypasses_client_list(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view_all'], []);

        $this->assertTrue(Auth::canAccessClient(999));
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
