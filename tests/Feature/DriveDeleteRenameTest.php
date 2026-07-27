<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Excluir e renomear na galeria do Drive (painel e portal).
 * Cobre guardas e validações que não dependem da API do Google: permissão,
 * escopo de agência/cliente, item inexistente e nome vazio. A mecânica
 * Drive+banco é a mesma dos dois lados (DriveUploadService).
 */
class DriveDeleteRenameTest extends FeatureTestCase
{
    // ── Painel: excluir ──────────────────────────────────────────────────────

    public function test_sem_sessao_nao_exclui(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/1/delete");

        $this->assertNotSame(200, $resp->getStatus());
    }

    public function test_sem_permissao_nao_exclui(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: []);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/1/delete");

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_excluir_arquivo_inexistente_e_404(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/9999/delete");

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_excluir_pasta_inexistente_e_404(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folder/9999/delete");

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_excluir_arquivo_de_outro_cliente_e_404(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $outro      = $this->createClient($agencyId, 'Outro Cliente');
        $fileAlheio = $this->createDriveFile($agencyId, $outro['id'], null, 'alheio.jpg');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/{$fileAlheio}/delete");

        $this->assertSame(404, $resp->getStatus());

        // O registro do outro cliente continua intacto.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM drive_files WHERE id = :id');
        $stmt->execute([':id' => $fileAlheio]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_restaurar_sem_drive_file_id_e_422(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/restore", ['drive_file_id' => '']);

        $this->assertSame(422, $resp->getStatus());
    }

    // ── Painel: renomear ─────────────────────────────────────────────────────

    public function test_renomear_com_nome_vazio_e_422(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $fileId = $this->createDriveFile($agencyId, $client['id'], null, 'foto.jpg');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/{$fileId}/rename", ['name' => '   ']);

        $this->assertSame(422, $resp->getStatus());
    }

    public function test_renomear_arquivo_inexistente_e_404(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/file/9999/rename", ['name' => 'Novo nome']);

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_renomear_pasta_inexistente_e_404(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folder/9999/rename", ['name' => 'Novo nome']);

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_renomear_pasta_de_outro_cliente_e_404(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $outro       = $this->createClient($agencyId, 'Outro Cliente');
        $pastaAlheia = $this->createDriveFolder($agencyId, $outro['id'], null, 'Pasta do outro');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/folder/{$pastaAlheia}/rename", ['name' => 'Invasão']);

        $this->assertSame(404, $resp->getStatus());

        $stmt = $this->pdo->prepare('SELECT name FROM drive_folders WHERE id = :id');
        $stmt->execute([':id' => $pastaAlheia]);
        $this->assertSame('Pasta do outro', $stmt->fetchColumn());
    }

    // ── Portal ───────────────────────────────────────────────────────────────

    public function test_portal_token_invalido_nao_renomeia(): void
    {
        $resp = $this->post('/portal/token-que-nao-existe/drive/file/1/rename', ['name' => 'X']);

        $this->assertNotSame(200, $resp->getStatus());
    }

    public function test_portal_renomear_nome_vazio_e_422(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $fileId   = $this->createDriveFile($agencyId, $client['id'], null, 'video.mp4');

        $resp = $this->post("/portal/{$client['portal_token']}/drive/file/{$fileId}/rename", ['name' => '']);

        $this->assertSame(422, $resp->getStatus());
    }

    public function test_portal_nao_renomeia_arquivo_de_outro_cliente(): void
    {
        $agencyId = $this->createAgency();
        $clientA  = $this->createClient($agencyId, 'Cliente A');
        $clientB  = $this->createClient($agencyId, 'Cliente B');
        $fileDoB  = $this->createDriveFile($agencyId, $clientB['id'], null, 'video-do-b.mp4');

        $resp = $this->post("/portal/{$clientA['portal_token']}/drive/file/{$fileDoB}/rename", ['name' => 'Invasão']);

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_portal_excluir_arquivo_inexistente_e_404(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/portal/{$client['portal_token']}/drive/file/9999/delete");

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_portal_restaurar_sem_drive_file_id_e_422(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/portal/{$client['portal_token']}/drive/file/restore", ['drive_file_id' => '']);

        $this->assertSame(422, $resp->getStatus());
    }

    // ── Fábricas ─────────────────────────────────────────────────────────────

    /** @return array{0: array{id:int,portal_token:string}, 1: int} */
    private function loggedInWithClient(): array
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: ['clients.view', 'clients.view_all']);

        return [$client, $agencyId];
    }

    private function createDriveFolder(int $agencyId, int $clientId, ?int $parentId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_folders (agency_id, client_id, parent_id, drive_folder_id, name, created_at)
             VALUES (:a, :c, :p, :d, :n, NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a' => $agencyId, ':c' => $clientId, ':p' => $parentId,
            ':d' => 'drive-folder-' . bin2hex(random_bytes(6)), ':n' => $name,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function createDriveFile(int $agencyId, int $clientId, ?int $folderId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_files (agency_id, client_id, folder_id, drive_file_id, name, mime_type, uploaded_via, created_at)
             VALUES (:a, :c, :f, :d, :n, 'image/jpeg', 'panel', NOW()) RETURNING id"
        );
        $stmt->execute([
            ':a' => $agencyId, ':c' => $clientId, ':f' => $folderId,
            ':d' => 'drive-file-' . bin2hex(random_bytes(6)), ':n' => $name,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
