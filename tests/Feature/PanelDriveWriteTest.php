<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * CONT-06: a equipe cria pastas e envia arquivos pela galeria interna do
 * cliente. Cobre as guardas que não dependem da API do Google: permissão,
 * escopo de agência e validação de entrada. (A mecânica Drive em si é a mesma
 * do portal — DriveUploadService — coberta pelos guards do DriveDirectUploadTest.)
 */
class PanelDriveWriteTest extends FeatureTestCase
{
    public function test_sem_sessao_nao_cria_pasta(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folders", ['name' => 'Pasta X']);

        // Sem login: AuthMiddleware manda pro login (redirect) — nunca 200.
        $this->assertNotSame(200, $resp->getStatus());
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: []); // nenhuma permissão

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folders", ['name' => 'Pasta X']);

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_cliente_de_outra_agencia_e_404(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clientB  = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA);
        $this->actingAs($userA['id'], permissions: ['clients.view', 'clients.view_all']);

        $resp = $this->post("/clientes/{$clientB['id']}/conteudos/folders", ['name' => 'Invasão']);

        $this->assertContains($resp->getStatus(), [403, 404], 'Cliente de outra agência não pode ser alcançado.');
    }

    public function test_nome_vazio_e_422(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folders", ['name' => '  ']);

        $this->assertSame(422, $resp->getStatus());
    }

    public function test_upload_session_valida_entrada_antes_de_chamar_o_drive(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);

        // Sem nome/tamanho → 422 antes de qualquer chamada externa.
        $resp = $this->post("/clientes/{$client['id']}/conteudos/upload/session", ['name' => '', 'size' => 0]);
        $this->assertSame(422, $resp->getStatus());

        // Subpasta inexistente → 404.
        $resp = $this->post("/clientes/{$client['id']}/conteudos/upload/session", [
            'name' => 'video.mp4', 'size' => 1024, 'folder_id' => 9999,
        ]);
        $this->assertSame(404, $resp->getStatus());
    }

    public function test_upload_complete_sem_drive_file_id_e_422(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/upload/complete", ['drive_file_id' => '']);

        $this->assertSame(422, $resp->getStatus());
    }
}
