<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClientService;
use App\Services\ContentPlanService;
use PHPUnit\Framework\TestCase;

/**
 * O link de aprovação que viaja por WhatsApp tem de ser o do PORTAL
 * (público, por token) — a rota interna /aprovacoes/{id} exige login e o
 * cliente não tem conta. Já aconteceu: cliente recebendo link que não abre.
 */
class PortalPlanUrlTest extends TestCase
{
    public function test_monta_o_link_publico_do_portal(): void
    {
        $url = ContentPlanService::portalPlanUrl('abc123', 42);

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/portal/abc123/planos/42', $url);
        $this->assertStringNotContainsString('/aprovacoes/', $url);
    }

    public function test_sem_token_nao_ha_link(): void
    {
        $this->assertNull(ContentPlanService::portalPlanUrl(null, 42));
        $this->assertNull(ContentPlanService::portalPlanUrl('', 42));
    }

    /**
     * Mesma classe de erro no relatório mensal (BUG-07): o link ia para
     * /executive-report, rota interna que exige login.
     */
    public function test_monta_o_link_publico_do_painel_do_cliente(): void
    {
        $url = ClientService::portalUrl('abc123');

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/portal/abc123', $url);
        $this->assertStringNotContainsString('executive-report', $url);
    }

    public function test_sem_token_nao_ha_link_do_painel(): void
    {
        $this->assertNull(ClientService::portalUrl(null));
        $this->assertNull(ClientService::portalUrl(''));
    }
}
