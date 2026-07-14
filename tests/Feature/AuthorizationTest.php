<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * QA-03 — autorização e isolamento multi-tenant **pela HTTP real**.
 *
 * Os testes unitários já verificam os middlewares isoladamente. Estes garantem
 * o que de fato importa comercialmente: numa requisição de verdade, passando
 * por rota + pipeline + controller + banco, um usuário da agência A **não**
 * consegue ver dado da agência B, e sem permissão ninguém entra.
 */
class AuthorizationTest extends FeatureTestCase
{
    public function test_visitante_sem_sessao_e_redirecionado_para_o_login(): void
    {
        $response = $this->get('/clientes');

        $this->assertSame(302, $response->getStatus());
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        // Logado, mas sem a permissão clients.view
        $this->actingAs($user['id'], permissions: []);

        $response = $this->get('/clientes');

        $this->assertSame(403, $response->getStatus());
    }

    public function test_usuario_com_permissao_lista_clientes(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);
        $this->createClient($agencyId, 'Cliente Visível');

        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);

        $response = $this->get('/clientes');

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Cliente Visível', $response->getBody());
    }

    /**
     * O teste que justifica o produto: vazamento entre tenants é o pior bug
     * possível num SaaS multi-agência.
     */
    public function test_usuario_nao_ve_cliente_de_outra_agencia(): void
    {
        $agencyA = $this->createAgency('Agência A');
        $agencyB = $this->createAgency('Agência B');

        $userA    = $this->createUser($agencyA, 'a@test.com');
        $clientB  = $this->createClient($agencyB, 'Cliente Secreto da B');

        $this->actingAs($userA['id'], permissions: ['clients.view', 'clients.view_all']);

        // Listagem não pode conter o cliente da outra agência…
        $list = $this->get('/clientes');
        $this->assertStringNotContainsString('Cliente Secreto da B', $list->getBody());

        // …e o acesso direto por ID tem de falhar (IDOR).
        $direct = $this->get('/clientes/' . $clientB['id']);
        $this->assertContains(
            $direct->getStatus(),
            [403, 404],
            'Acesso direto a cliente de outra agência precisa ser negado.'
        );
        $this->assertStringNotContainsString('Cliente Secreto da B', $direct->getBody());
    }

    public function test_area_de_plataforma_e_negada_a_usuario_de_tenant(): void
    {
        $agencyId = $this->createAgency();
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: ['clients.view']);

        $response = $this->get('/admin/tenants');

        $this->assertContains($response->getStatus(), [403, 302]);
    }
}
