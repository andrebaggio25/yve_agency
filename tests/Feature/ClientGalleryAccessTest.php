<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * A galeria do Drive do cliente é onde social media e designer pegam e sobem
 * material. O backend já os autorizava (`clients.view`), mas o único link para
 * a tela estava escondido atrás de `clients.edit` — e, sem `clients.view_all`,
 * o ClientAccessMiddleware barrava a rota.
 *
 * Cobre também o dado que alimenta o botão "Copiar link da pasta".
 */
class ClientGalleryAccessTest extends FeatureTestCase
{
    /** Permissões efetivas do papel designer depois do backfill de RBAC. */
    private const PERM_DESIGNER = [
        'dashboard.view', 'clients.view', 'clients.view_all',
        'content.view', 'drive_assets.view', 'approvals.view', 'tasks.view',
    ];

    public function test_designer_abre_a_galeria_do_cliente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId, 'Cliente Gama');
        $user     = $this->createUser($agencyId);

        // Sem vínculo em client_user_access: quem sustenta o acesso é view_all.
        $this->actingAs($user['id'], permissions: self::PERM_DESIGNER, clientIds: []);

        $response = $this->get("/clientes/{$client['id']}/conteudos");

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Conteúdos do cliente', $response->getBody());
    }

    public function test_link_da_galeria_aparece_para_quem_nao_edita_cliente(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: self::PERM_DESIGNER, clientIds: []);

        $body = $this->get("/clientes/{$client['id']}")->getBody();

        $this->assertStringContainsString("/clientes/{$client['id']}/conteudos", $body);
        // …e sem clients.edit as ações de cadastro continuam fora.
        $this->assertStringNotContainsString("/clientes/{$client['id']}/editar", $body);
    }

    public function test_sem_drive_assets_view_o_link_da_galeria_nao_aparece(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);

        $this->actingAs($user['id'], permissions: ['dashboard.view', 'clients.view', 'clients.view_all'], clientIds: []);

        $body = $this->get("/clientes/{$client['id']}")->getBody();

        $this->assertStringNotContainsString("/clientes/{$client['id']}/conteudos", $body);
    }

    /** O botão "Copiar link da pasta" depende destes dois campos no JSON. */
    public function test_listagem_de_pastas_devolve_o_id_da_pasta_no_drive(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);

        $this->pdo->prepare('UPDATE clients SET drive_folder_id = :d WHERE id = :id')
            ->execute([':d' => 'raiz-do-cliente-123', ':id' => $client['id']]);

        $this->pdo->prepare(
            "INSERT INTO drive_folders (agency_id, client_id, parent_id, drive_folder_id, name, created_at)
             VALUES (:a, :c, NULL, 'subpasta-abc', 'Setembro', NOW())"
        )->execute([':a' => $agencyId, ':c' => $client['id']]);

        $this->actingAs($user['id'], permissions: self::PERM_DESIGNER, clientIds: []);

        $response = $this->get("/clientes/{$client['id']}/conteudos/folders");
        $payload  = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('raiz-do-cliente-123', $payload['folder_drive_id']);
        $this->assertSame('subpasta-abc', $payload['folders'][0]['drive_folder_id']);
    }

    public function test_cliente_de_outra_agencia_continua_negado_na_galeria(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clientB  = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA);

        $this->actingAs($userA['id'], permissions: self::PERM_DESIGNER, clientIds: []);

        $response = $this->get("/clientes/{$clientB['id']}/conteudos");

        $this->assertContains($response->getStatus(), [403, 404]);
    }
}
