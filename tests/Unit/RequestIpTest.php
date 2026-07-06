<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Trava o SEC-02: o IP usado para rate limit/decisões de segurança não pode ser
 * ditado por um cabeçalho forjável. X-Forwarded-For só vale vindo de um proxy
 * confiável declarado em TRUSTED_PROXIES; caso contrário, usa-se REMOTE_ADDR.
 */
class RequestIpTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_FILES = $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/login';
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function test_uses_remote_addr_and_ignores_forwarded_by_default(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

        $this->assertSame('203.0.113.9', Request::fromGlobals()->ip());
    }

    public function test_ignores_forwarded_when_remote_addr_is_not_a_trusted_proxy(): void
    {
        $_ENV['TRUSTED_PROXIES']         = '10.0.0.1';
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9'; // não é o proxy confiável
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

        $this->assertSame('203.0.113.9', Request::fromGlobals()->ip());
    }

    public function test_honors_forwarded_first_hop_from_trusted_proxy(): void
    {
        $_ENV['TRUSTED_PROXIES']         = '10.0.0.1, 10.0.0.2';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 10.0.0.1';

        // O primeiro hop é o cliente original.
        $this->assertSame('1.2.3.4', Request::fromGlobals()->ip());
    }

    public function test_falls_back_to_default_when_no_remote_addr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame('0.0.0.0', Request::fromGlobals()->ip());
    }
}
