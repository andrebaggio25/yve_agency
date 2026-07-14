<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * QA-03 — o portal do cliente pela HTTP real.
 *
 * O portal é a superfície mais exposta do produto: público, sem login,
 * autenticado por um token que viaja na URL e é compartilhado por WhatsApp.
 * Aqui garantimos as três coisas que não podem falhar: token inválido não
 * entra, portal desligado não abre, e cliente não enxerga dado de outro.
 */
class PortalTest extends FeatureTestCase
{
    public function test_token_invalido_nao_da_acesso(): void
    {
        $this->createAgency();

        $response = $this->get('/portal/token-que-nao-existe');

        $this->assertSame(403, $response->getStatus());
    }

    public function test_portal_desativado_nao_abre(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Off', portal: false);

        $response = $this->get('/portal/' . $client['portal_token']);

        $this->assertSame(403, $response->getStatus());
    }

    public function test_token_valido_abre_o_portal_do_cliente_certo(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Dono do Token');
        $outro    = $this->createClient($agencyId, 'Cliente Alheio');

        $response = $this->get('/portal/' . $client['portal_token']);

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Cliente Dono do Token', $response->getBody());
        $this->assertStringNotContainsString('Cliente Alheio', $response->getBody());
    }

    /**
     * SEC-08: sem CSRF, o link do portal (que circula por e-mail/WhatsApp)
     * bastava para um site hostil forjar uma mutação em nome do cliente.
     */
    public function test_mutacao_do_portal_sem_csrf_e_rejeitada(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $response = $this->post(
            '/portal/' . $client['portal_token'] . '/drive/folders',
            ['name' => 'Pasta Forjada'],
            withCsrf: false
        );

        $this->assertSame(419, $response->getStatus());
    }

    public function test_mutacao_do_portal_com_csrf_passa_da_validacao(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $response = $this->post(
            '/portal/' . $client['portal_token'] . '/drive/folders',
            ['name' => 'Pasta Legítima']
        );

        // Não é 419: o CSRF passou. O Drive não está conectado neste tenant de
        // teste, então o erro esperado é de integração (500), não de segurança.
        $this->assertNotSame(419, $response->getStatus());
    }
}
