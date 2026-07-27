<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Mover arquivos/pastas na galeria do Drive (painel e portal).
 * Cobre as guardas e os caminhos que não dependem da API do Google: permissão,
 * escopo de agência, validação de entrada, no-op (item já no destino) e a
 * proteção contra ciclo (pasta dentro dela mesma / de um descendente).
 */
class DriveMoveTest extends FeatureTestCase
{
    // ── Painel ───────────────────────────────────────────────────────────────

    public function test_sem_sessao_nao_move(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", ['file_ids' => [1]]);

        $this->assertNotSame(200, $resp->getStatus());
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: []);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", ['file_ids' => [1]]);

        $this->assertSame(403, $resp->getStatus());
    }

    public function test_cliente_de_outra_agencia_e_bloqueado(): void
    {
        $agenciaA = $this->createAgency('Agência A');
        $agenciaB = $this->createAgency('Agência B');
        $clientB  = $this->createClient($agenciaB, 'Cliente da B');
        $userA    = $this->createUser($agenciaA);
        $this->actingAs($userA['id'], permissions: ['clients.view', 'clients.view_all']);

        $resp = $this->post("/clientes/{$clientB['id']}/conteudos/move", ['file_ids' => [1]]);

        $this->assertContains($resp->getStatus(), [403, 404]);
    }

    public function test_sem_itens_selecionados_e_422(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", ['file_ids' => [], 'folder_ids' => []]);

        $this->assertSame(422, $resp->getStatus());
    }

    public function test_destino_inexistente_e_404(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", [
            'file_ids'         => [1],
            'target_folder_id' => 9999,
        ]);

        $this->assertSame(404, $resp->getStatus());
    }

    public function test_arquivo_ja_no_destino_e_noop_de_sucesso(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $folderId = $this->createDriveFolder($agencyId, $client['id'], null, 'Pasta A');
        $fileId   = $this->createDriveFile($agencyId, $client['id'], $folderId, 'foto.jpg');

        // Já está na Pasta A: não chama o Drive, conta como movido.
        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", [
            'file_ids'         => [$fileId],
            'target_folder_id' => $folderId,
        ]);
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(1, $data['moved']);
        $this->assertSame([], $data['errors']);
    }

    public function test_mover_pasta_para_dentro_dela_mesma_e_erro(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $folderId = $this->createDriveFolder($agencyId, $client['id'], null, 'Pasta A');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", [
            'folder_ids'       => [$folderId],
            'target_folder_id' => $folderId,
        ]);
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(0, $data['moved']);
        $this->assertCount(1, $data['errors']);

        // O banco não mudou.
        $stmt = $this->pdo->prepare('SELECT parent_id FROM drive_folders WHERE id = :id');
        $stmt->execute([':id' => $folderId]);
        $this->assertNull($stmt->fetchColumn());
    }

    public function test_mover_pasta_para_um_descendente_e_erro(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $pai   = $this->createDriveFolder($agencyId, $client['id'], null, 'Pai');
        $filha = $this->createDriveFolder($agencyId, $client['id'], $pai, 'Filha');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", [
            'folder_ids'       => [$pai],
            'target_folder_id' => $filha,
        ]);
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(0, $data['moved']);
        $this->assertCount(1, $data['errors']);

        $stmt = $this->pdo->prepare('SELECT parent_id FROM drive_folders WHERE id = :id');
        $stmt->execute([':id' => $pai]);
        $this->assertNull($stmt->fetchColumn(), 'A pasta pai não pode ter sido movida.');
    }

    public function test_arquivo_de_outro_cliente_nao_e_movido(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $outroCliente = $this->createClient($agencyId, 'Outro Cliente');
        $folderId     = $this->createDriveFolder($agencyId, $client['id'], null, 'Destino');
        $fileAlheio   = $this->createDriveFile($agencyId, $outroCliente['id'], null, 'alheio.jpg');

        $resp = $this->post("/clientes/{$client['id']}/conteudos/move", [
            'file_ids'         => [$fileAlheio],
            'target_folder_id' => $folderId,
        ]);
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(0, $data['moved']);
        $this->assertCount(1, $data['errors'], 'Arquivo de outro cliente aparece como não encontrado.');

        $stmt = $this->pdo->prepare('SELECT folder_id FROM drive_files WHERE id = :id');
        $stmt->execute([':id' => $fileAlheio]);
        $this->assertNull($stmt->fetchColumn());
    }

    // ── Portal ───────────────────────────────────────────────────────────────

    public function test_portal_token_invalido_nao_move(): void
    {
        $resp = $this->post('/portal/token-que-nao-existe/drive/move', ['file_ids' => [1]]);

        $this->assertNotSame(200, $resp->getStatus());
    }

    public function test_portal_sem_itens_e_422(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $resp = $this->post("/portal/{$client['portal_token']}/drive/move", ['file_ids' => []]);

        $this->assertSame(422, $resp->getStatus());
    }

    public function test_portal_noop_move_funciona(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $folderId = $this->createDriveFolder($agencyId, $client['id'], null, 'Pasta A');
        $fileId   = $this->createDriveFile($agencyId, $client['id'], $folderId, 'video.mp4');

        $resp = $this->post("/portal/{$client['portal_token']}/drive/move", [
            'file_ids'         => [$fileId],
            'target_folder_id' => $folderId,
        ]);
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(1, $data['moved']);
    }

    public function test_portal_nao_alcanca_pasta_de_outro_cliente(): void
    {
        $agencyId  = $this->createAgency();
        $clientA   = $this->createClient($agencyId, 'Cliente A');
        $clientB   = $this->createClient($agencyId, 'Cliente B');
        $pastaDoB  = $this->createDriveFolder($agencyId, $clientB['id'], null, 'Pasta do B');

        $resp = $this->post("/portal/{$clientA['portal_token']}/drive/move", [
            'file_ids'         => [1],
            'target_folder_id' => $pastaDoB,
        ]);

        $this->assertSame(404, $resp->getStatus());
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
