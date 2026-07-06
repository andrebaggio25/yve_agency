<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\ClientAccessMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\PlatformAdminMiddleware;
use App\Support\Auth;
use PHPUnit\Framework\TestCase;

/**
 * QA-02: garante que a camada de ENFORCEMENT de autorização funciona — não basta
 * o Auth calcular a permissão, o middleware precisa barrar (403/401/redirect) ou
 * deixar passar. Cada regra tem caso positivo E negativo.
 */
class MiddlewareAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── AuthMiddleware ────────────────────────────────────────────────────────

    public function test_auth_redirects_guest_to_login(): void
    {
        $resp = (new AuthMiddleware())->handle($this->request(), $this->passthrough());

        $this->assertSame(302, $resp->getStatus());
        $this->assertSame('/login', $resp->getHeaders()['Location'] ?? null);
    }

    public function test_auth_returns_401_json_for_guest_api(): void
    {
        $resp = (new AuthMiddleware())->handle($this->request(json: true), $this->passthrough());
        $this->assertSame(401, $resp->getStatus());
    }

    public function test_auth_passes_authenticated_user(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], []);
        $resp = (new AuthMiddleware())->handle($this->request(), $this->passthrough());

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame('PASSED', $resp->getBody());
    }

    // ── PermissionMiddleware ──────────────────────────────────────────────────

    public function test_permission_blocks_user_without_permission(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view'], []);
        $resp = (new PermissionMiddleware('clients.delete'))->handle($this->request(json: true), $this->passthrough());

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_permission_allows_user_with_permission(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.delete'], []);
        $resp = (new PermissionMiddleware('clients.delete'))->handle($this->request(), $this->passthrough());

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame('PASSED', $resp->getBody());
    }

    // ── ClientAccessMiddleware ────────────────────────────────────────────────

    public function test_client_access_blocks_client_not_in_scope(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [10, 20]);
        $resp = (new ClientAccessMiddleware())->handle(
            $this->request(json: true, params: ['clientId' => '99']),
            $this->passthrough(),
        );

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_client_access_allows_client_in_scope(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], [], [10, 20]);
        $resp = (new ClientAccessMiddleware())->handle(
            $this->request(params: ['clientId' => '10']),
            $this->passthrough(),
        );

        $this->assertSame(200, $resp->getStatus());
    }

    public function test_client_access_view_all_bypasses_scope(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X'], ['clients.view_all'], []);
        $resp = (new ClientAccessMiddleware())->handle(
            $this->request(params: ['clientId' => '999']),
            $this->passthrough(),
        );

        $this->assertSame(200, $resp->getStatus());
    }

    // ── PlatformAdminMiddleware ───────────────────────────────────────────────

    public function test_platform_admin_blocks_regular_tenant_user(): void
    {
        Auth::login(['id' => 1, 'agency_id' => 1, 'name' => 'X', 'is_platform_admin' => false], ['clients.view'], []);
        $resp = (new PlatformAdminMiddleware())->handle($this->request(json: true), $this->passthrough());

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_platform_admin_allows_platform_admin(): void
    {
        Auth::login(['id' => 1, 'agency_id' => null, 'name' => 'Root', 'is_platform_admin' => true], [], []);
        $resp = (new PlatformAdminMiddleware())->handle($this->request(), $this->passthrough());

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame('PASSED', $resp->getBody());
    }

    public function test_platform_admin_redirects_guest(): void
    {
        $resp = (new PlatformAdminMiddleware())->handle($this->request(), $this->passthrough());
        $this->assertSame(302, $resp->getStatus());
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** @param array<string,string> $params */
    private function request(string $method = 'GET', bool $json = false, array $params = []): Request
    {
        $_GET = $_POST = $_FILES = $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['HTTP_ACCEPT']    = $json ? 'application/json' : 'text/html';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $req = Request::fromGlobals();
        if ($params) {
            $req->setParams($params);
        }
        return $req;
    }

    private function passthrough(): \Closure
    {
        return static fn(Request $req): Response => Response::text('PASSED', 200);
    }
}
