<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DriveSyncService;
use App\Services\GoogleDriveApiService;

/**
 * BUG-03: sincronizar a galeria não pode apagá-la.
 *
 * O sync reconcilia o banco com o Drive, então "o Drive não tem nada" significa
 * "apague tudo". Com a listagem falhando em silêncio (ou com o escopo
 * drive.file enxergando nada após trocar a conta Google), isso limpava os
 * `drive_files` do cliente. Aqui cobrimos a trava: remoto vazio com banco cheio
 * aborta e reporta, erro do Google não remove nada, e escrever na galeria
 * exige `content.edit` (não basta `clients.view`, que é leitura).
 *
 * O Google é trocado por um fake no container — nenhum teste fala com a rede.
 */
class DriveSyncTest extends FeatureTestCase
{
    private const PERM_ESCRITA = ['clients.view', 'clients.view_all', 'content.edit'];
    private const PERM_LEITURA = ['clients.view', 'clients.view_all', 'content.view', 'drive_assets.view'];

    // ── A trava ──────────────────────────────────────────────────────────────

    /** Caso positivo: o sync faz o seu trabalho (remove o que sumiu de verdade). */
    public function test_sync_remove_do_banco_o_arquivo_que_sumiu_do_drive(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $ficou = $this->createDriveFile($agencyId, $client['id'], null, 'fica.jpg', 'DRIVE-A');
        $sumiu = $this->createDriveFile($agencyId, $client['id'], null, 'sumiu.jpg', 'DRIVE-B');

        $this->fakeDrive(['RAIZ' => [$this->remoteFile('DRIVE-A', 'fica.jpg')]]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(1, $data['removed']);
        $this->assertTrue($this->driveFileExists($ficou));
        $this->assertFalse($this->driveFileExists($sumiu));
    }

    public function test_drive_vazio_com_banco_cheio_aborta_sem_remover_nada(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $a = $this->createDriveFile($agencyId, $client['id'], null, 'a.jpg', 'DRIVE-A');
        $b = $this->createDriveFile($agencyId, $client['id'], null, 'b.jpg', 'DRIVE-B');

        // O Drive responde 200 com zero itens (conta trocada, escopo perdido).
        $this->fakeDrive(['RAIZ' => []]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(409, $resp->getStatus());
        $this->assertFalse($data['success']);
        $this->assertSame(0, $data['removed']);
        $this->assertNotSame('', trim((string) $data['error']));
        $this->assertTrue($this->driveFileExists($a), 'A galeria não pode ser apagada.');
        $this->assertTrue($this->driveFileExists($b), 'A galeria não pode ser apagada.');
    }

    public function test_subpasta_vazia_no_drive_nao_apaga_a_subarvore(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $pasta   = $this->createDriveFolder($agencyId, $client['id'], null, 'Setembro', 'DRIVE-PASTA');
        $arquivo = $this->createDriveFile($agencyId, $client['id'], $pasta, 'post.jpg', 'DRIVE-A');

        // A raiz lista a pasta; a pasta volta vazia — suspeito, aborta.
        $this->fakeDrive([
            'RAIZ'        => [$this->remoteFolder('DRIVE-PASTA', 'Setembro')],
            'DRIVE-PASTA' => [],
        ]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");

        $this->assertSame(409, $resp->getStatus());
        $this->assertTrue($this->driveFileExists($arquivo));
        $this->assertSame(1, $this->countFolders($pasta));
    }

    /**
     * A trava é por pasta, não por cliente: a pasta suspeita é ignorada e o
     * resto da galeria continua sincronizando. Antes, o primeiro abort parava
     * o cliente inteiro — e, como o registro fantasma fica no banco, todo
     * clique seguinte em Sincronizar parava no mesmo lugar.
     */
    public function test_pasta_suspeita_e_ignorada_sem_travar_o_resto_do_cliente(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $setembro = $this->createDriveFolder($agencyId, $client['id'], null, 'Setembro', 'DRIVE-SETEMBRO');
        $fantasma = $this->createDriveFile($agencyId, $client['id'], $setembro, 'fantasma.jpg', 'DRIVE-GHOST');
        $outubro  = $this->createDriveFolder($agencyId, $client['id'], null, 'Outubro', 'DRIVE-OUTUBRO');
        $sumiu    = $this->createDriveFile($agencyId, $client['id'], $outubro, 'sumiu.jpg', 'DRIVE-X');
        $fica     = $this->createDriveFile($agencyId, $client['id'], $outubro, 'fica.jpg', 'DRIVE-Y');

        // Setembro vem primeiro na listagem: antes da correção, o abort nela
        // enterrava as mudanças de Outubro.
        $this->fakeDrive([
            'RAIZ' => [
                $this->remoteFolder('DRIVE-SETEMBRO', 'Setembro'),
                $this->remoteFolder('DRIVE-OUTUBRO', 'Outubro'),
            ],
            'DRIVE-SETEMBRO' => [],
            'DRIVE-OUTUBRO'  => [$this->remoteFile('DRIVE-Y', 'fica.jpg')],
        ]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(409, $resp->getStatus());
        $this->assertTrue($this->driveFileExists($fantasma), 'Nada é apagado na pasta suspeita.');
        $this->assertFalse($this->driveFileExists($sumiu), 'As outras pastas seguem sincronizando.');
        $this->assertTrue($this->driveFileExists($fica));
        $this->assertSame(1, $data['removed']);
        $this->assertSame(1, $data['skipped'], 'Uma pasta ignorada.');
        // Sem o caminho, o usuário não sabe em qual pasta conferir o Drive.
        $this->assertSame(['/Setembro'], $data['skipped_folders']);
        $this->assertStringContainsString('/Setembro', (string) $data['error']);
    }

    /** Pasta ignorada na raiz também é identificável no relatório. */
    public function test_pasta_ignorada_na_raiz_aparece_como_barra(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $this->createDriveFile($agencyId, $client['id'], null, 'a.jpg', 'DRIVE-A');

        $this->fakeDrive(['RAIZ' => []]);

        $data = json_decode($this->post("/clientes/{$client['id']}/conteudos/sync")->getBody(), true);

        $this->assertSame(['/'], $data['skipped_folders']);
    }

    /** O cron também diz QUAL pasta ficou de fora, e em qual cliente. */
    public function test_sync_all_diz_qual_pasta_ignorou(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $pasta = $this->createDriveFolder($agencyId, $client['id'], null, 'Setembro', 'DRIVE-SETEMBRO');
        $this->createDriveFile($agencyId, $client['id'], $pasta, 'fantasma.jpg', 'DRIVE-GHOST');

        $this->fakeDrive([
            'RAIZ'           => [$this->remoteFolder('DRIVE-SETEMBRO', 'Setembro')],
            'DRIVE-SETEMBRO' => [],
        ]);

        $totals = $this->container->make(DriveSyncService::class)->syncAll();

        $this->assertSame(1, $totals['skipped']);
        $this->assertSame(['#' . $client['id'] . ' /Setembro'], $totals['skipped_folders']);
    }

    /**
     * O contador que a tela e o cron mostram conta LINHAS removidas. Pasta que
     * sumiu do Drive leva a subárvore inteira: reportar "1 removido" esconderia
     * justamente a perda em massa que a trava existe para tornar visível.
     */
    public function test_removed_conta_as_linhas_da_subarvore_removida(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $setembro = $this->createDriveFolder($agencyId, $client['id'], null, 'Setembro', 'DRIVE-SETEMBRO');
        $stories  = $this->createDriveFolder($agencyId, $client['id'], $setembro, 'Stories', 'DRIVE-STORIES');
        $this->createDriveFile($agencyId, $client['id'], $setembro, 'a.jpg', 'DRIVE-A');
        $this->createDriveFile($agencyId, $client['id'], $setembro, 'b.jpg', 'DRIVE-B');
        $this->createDriveFile($agencyId, $client['id'], $stories, 'c.jpg', 'DRIVE-C');

        // Outubro continua no Drive: a raiz não volta vazia, então a trava não
        // entra e a reconciliação apaga a subárvore de Setembro.
        $outubro = $this->createDriveFolder($agencyId, $client['id'], null, 'Outubro', 'DRIVE-OUTUBRO');
        $mantem  = $this->createDriveFile($agencyId, $client['id'], $outubro, 'd.jpg', 'DRIVE-D');

        $this->fakeDrive([
            'RAIZ'          => [$this->remoteFolder('DRIVE-OUTUBRO', 'Outubro')],
            'DRIVE-OUTUBRO' => [$this->remoteFile('DRIVE-D', 'd.jpg')],
        ]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        // 2 pastas (Setembro + Stories) + 3 arquivos.
        $this->assertSame(5, $data['removed']);
        $this->assertSame(0, $this->countFolders($setembro));
        $this->assertSame(0, $this->countFolders($stories));
        $this->assertTrue($this->driveFileExists($mantem));
    }

    public function test_falha_do_google_nao_remove_nada(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $a = $this->createDriveFile($agencyId, $client['id'], null, 'a.jpg', 'DRIVE-A');

        $fake = $this->fakeDrive([]);
        $fake->failWith = 'HTTP 429 do Google';

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");

        $this->assertSame(500, $resp->getStatus());
        $this->assertTrue($this->driveFileExists($a));
    }

    /**
     * O critério do item: Drive respondendo 429 não remove nenhuma linha.
     * Aqui o fake intercepta o HTTP, não o `listFolder` — então a listagem
     * real roda, esgota o retry e a exceção sobe até a tela.
     */
    public function test_drive_em_429_nao_remove_nenhuma_linha(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $a = $this->createDriveFile($agencyId, $client['id'], null, 'a.jpg', 'DRIVE-A');
        $b = $this->createDriveFile($agencyId, $client['id'], null, 'b.jpg', 'DRIVE-B');

        $fake = new FakeRateLimitedDriveApi();
        $this->container->instance(GoogleDriveApiService::class, $fake);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");

        $this->assertSame(500, $resp->getStatus());
        $this->assertSame(3, $fake->calls, 'Uma tentativa + 2 retries.');
        $this->assertTrue($this->driveFileExists($a));
        $this->assertTrue($this->driveFileExists($b));
    }

    /**
     * A varredura é incremental e sem transação: o que foi removido antes da
     * falha FICA removido. Então o erro volta COM os contadores — a resposta
     * dizia só "falhou", e a mensagem afirmava que nada havia mudado.
     */
    public function test_falha_de_listagem_no_meio_reporta_o_que_ja_foi_removido(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $antigo  = $this->createDriveFile($agencyId, $client['id'], null, 'antigo.jpg', 'DRIVE-OLD');
        $outubro = $this->createDriveFolder($agencyId, $client['id'], null, 'Outubro', 'DRIVE-OUTUBRO');
        $this->createDriveFile($agencyId, $client['id'], $outubro, 'post.jpg', 'DRIVE-A');

        // A raiz responde (e `antigo.jpg` sai do banco); a subpasta leva 503.
        $fake = $this->fakeDrive(['RAIZ' => [$this->remoteFolder('DRIVE-OUTUBRO', 'Outubro')]]);
        $fake->failFolders = ['DRIVE-OUTUBRO'];

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(500, $resp->getStatus());
        $this->assertFalse($this->driveFileExists($antigo), 'A remoção da raiz foi aplicada mesmo assim.');
        $this->assertSame(1, $data['removed'], 'O que já foi removido tem de aparecer no relatório.');
        $this->assertStringContainsString('503', (string) $data['error']);
        $this->assertStringContainsString('1 removido', (string) $data['error']);
    }

    /** Trava numa pasta + falha em outra: a resposta não esconde nenhuma das duas. */
    public function test_pasta_ignorada_e_falha_aparecem_na_mesma_resposta(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $setembro = $this->createDriveFolder($agencyId, $client['id'], null, 'Setembro', 'DRIVE-SETEMBRO');
        $fantasma = $this->createDriveFile($agencyId, $client['id'], $setembro, 'fantasma.jpg', 'DRIVE-GHOST');
        $outubro  = $this->createDriveFolder($agencyId, $client['id'], null, 'Outubro', 'DRIVE-OUTUBRO');
        $this->createDriveFile($agencyId, $client['id'], $outubro, 'post.jpg', 'DRIVE-A');

        $fake = $this->fakeDrive([
            'RAIZ' => [
                $this->remoteFolder('DRIVE-SETEMBRO', 'Setembro'),
                $this->remoteFolder('DRIVE-OUTUBRO', 'Outubro'),
            ],
            'DRIVE-SETEMBRO' => [],
        ]);
        $fake->failFolders = ['DRIVE-OUTUBRO'];

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(500, $resp->getStatus());
        $this->assertSame(1, $data['skipped']);
        $this->assertStringContainsString('503', (string) $data['error']);
        $this->assertStringContainsString('/Setembro', (string) $data['error']);
        $this->assertTrue($this->driveFileExists($fantasma));
    }

    /** No cron é o mesmo: cliente que falhou no meio conta o que aplicou. */
    public function test_sync_all_mantem_o_que_aplicou_no_cliente_que_falhou(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $antigo  = $this->createDriveFile($agencyId, $client['id'], null, 'antigo.jpg', 'DRIVE-OLD');
        $outubro = $this->createDriveFolder($agencyId, $client['id'], null, 'Outubro', 'DRIVE-OUTUBRO');
        $this->createDriveFile($agencyId, $client['id'], $outubro, 'post.jpg', 'DRIVE-A');

        $fake = $this->fakeDrive(['RAIZ' => [$this->remoteFolder('DRIVE-OUTUBRO', 'Outubro')]]);
        $fake->failFolders = ['DRIVE-OUTUBRO'];

        $totals = $this->container->make(DriveSyncService::class)->syncAll();

        $this->assertSame(1, $totals['errors']);
        $this->assertSame(1, $totals['removed'], 'Remoção já gravada não pode sair do total.');
        $this->assertFalse($this->driveFileExists($antigo));
        $this->assertStringContainsString('#' . $client['id'], implode(' ', $totals['failed']));
    }

    public function test_pasta_vazia_dos_dois_lados_sincroniza_normalmente(): void
    {
        [$client, ] = $this->loggedInWithClient();

        $this->fakeDrive(['RAIZ' => []]);

        $resp = $this->post("/clientes/{$client['id']}/conteudos/sync");
        $data = json_decode($resp->getBody(), true);

        $this->assertSame(200, $resp->getStatus());
        $this->assertSame(0, $data['removed']);
        $this->assertSame(0, $data['added']);
    }

    /** O cron reporta a pasta ignorada em vez de esvaziar a galeria calado. */
    public function test_sync_all_conta_a_pasta_ignorada(): void
    {
        [$client, $agencyId] = $this->loggedInWithClient();
        $a = $this->createDriveFile($agencyId, $client['id'], null, 'a.jpg', 'DRIVE-A');
        $this->fakeDrive(['RAIZ' => []]);

        $totals = $this->container->make(DriveSyncService::class)->syncAll();

        $this->assertSame(1, $totals['clients']);
        $this->assertSame(1, $totals['skipped']);
        $this->assertSame(0, $totals['removed']);
        $this->assertSame(0, $totals['errors']);
        $this->assertTrue($this->driveFileExists($a));
    }

    // ── Permissão de escrita ─────────────────────────────────────────────────

    /**
     * `clients.view` é leitura: perfil só-leitura (o Gestor de Tráfego, por
     * exemplo) não escreve nem sincroniza a galeria. O par positivo de cada
     * rota está nos testes acima e em PanelDriveWriteTest/DriveDeleteRenameTest.
     */
    public function test_perfil_so_leitura_recebe_403_na_escrita(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM_LEITURA);

        $rotas = [
            "/clientes/{$client['id']}/conteudos/sync",
            "/clientes/{$client['id']}/conteudos/folders",
            "/clientes/{$client['id']}/conteudos/upload/session",
            "/clientes/{$client['id']}/conteudos/upload/complete",
            "/clientes/{$client['id']}/conteudos/move",
            "/clientes/{$client['id']}/conteudos/file/1/delete",
            "/clientes/{$client['id']}/conteudos/folder/1/delete",
            "/clientes/{$client['id']}/conteudos/file/restore",
            "/clientes/{$client['id']}/conteudos/file/1/rename",
            "/clientes/{$client['id']}/conteudos/folder/1/rename",
        ];

        foreach ($rotas as $rota) {
            $this->assertSame(403, $this->post($rota, ['name' => 'X'])->getStatus(), $rota);
        }
    }

    /** Leitura continua aberta a quem não escreve — e sem o botão Sincronizar. */
    public function test_perfil_so_leitura_abre_a_galeria_sem_acoes_de_escrita(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->connectDrive($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM_LEITURA);

        $body = $this->get("/clientes/{$client['id']}/conteudos")->getBody();

        // Marcadores do próprio botão: os rótulos soltos ("Enviar arquivos")
        // também vivem no blob de i18n do JS e dariam falso positivo.
        $this->assertStringContainsString('Conteúdos do cliente', $body);
        $this->assertStringNotContainsString('@click="sync()"', $body);
        $this->assertStringNotContainsString('type="file"', $body);
        $this->assertStringNotContainsString('openMove()', $body);
    }

    public function test_quem_tem_content_edit_ve_as_acoes_de_escrita(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->connectDrive($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM_ESCRITA);

        $body = $this->get("/clientes/{$client['id']}/conteudos")->getBody();

        $this->assertStringContainsString('@click="sync()"', $body);
        $this->assertStringContainsString('type="file"', $body);
        $this->assertStringContainsString('openMove()', $body);
    }

    // ── Fábricas ─────────────────────────────────────────────────────────────

    /** @return array{0: array{id:int,portal_token:string}, 1: int} */
    private function loggedInWithClient(): array
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);
        $user     = $this->createUser($agencyId);
        $this->actingAs($user['id'], permissions: self::PERM_ESCRITA);

        // A pasta do cliente no Drive — sem ela o sync devolve 'no_folder'.
        $this->pdo->prepare('UPDATE clients SET drive_folder_id = :d WHERE id = :id')
            ->execute([':d' => 'RAIZ', ':id' => $client['id']]);
        $this->connectDrive($agencyId);

        return [$client, $agencyId];
    }

    /** Troca o Google por um dublê: o container resolve controller e service. */
    private function fakeDrive(array $listings): FakeDriveApi
    {
        $fake = new FakeDriveApi();
        $fake->listings = $listings;
        $this->container->instance(GoogleDriveApiService::class, $fake);

        return $fake;
    }

    private function connectDrive(int $agencyId): void
    {
        $this->pdo->prepare(
            "INSERT INTO google_drive_integrations (agency_id, refresh_token, root_folder_id, status, created_at)
             VALUES (:a, 'refresh-de-teste', 'ROOT', 'active', NOW())"
        )->execute([':a' => $agencyId]);
    }

    private function remoteFile(string $driveId, string $name): array
    {
        return ['id' => $driveId, 'name' => $name, 'mimeType' => 'image/jpeg'];
    }

    private function remoteFolder(string $driveId, string $name): array
    {
        return ['id' => $driveId, 'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];
    }

    private function createDriveFolder(int $agencyId, int $clientId, ?int $parentId, string $name, string $driveId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_folders (agency_id, client_id, parent_id, drive_folder_id, name, created_at)
             VALUES (:a, :c, :p, :d, :n, NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':p' => $parentId, ':d' => $driveId, ':n' => $name]);

        return (int) $stmt->fetchColumn();
    }

    private function createDriveFile(int $agencyId, int $clientId, ?int $folderId, string $name, string $driveId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drive_files (agency_id, client_id, folder_id, drive_file_id, name, mime_type, uploaded_via, created_at)
             VALUES (:a, :c, :f, :d, :n, 'image/jpeg', 'panel', NOW()) RETURNING id"
        );
        $stmt->execute([':a' => $agencyId, ':c' => $clientId, ':f' => $folderId, ':d' => $driveId, ':n' => $name]);

        return (int) $stmt->fetchColumn();
    }

    private function driveFileExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM drive_files WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return ((int) $stmt->fetchColumn()) === 1;
    }

    private function countFolders(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM drive_folders WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}

/**
 * Dublê do Google Drive: listagem enlatada por pasta, sem rede.
 * Pasta que não está no mapa volta vazia — é justamente o caso que a trava
 * do BUG-03 tem de barrar.
 */
final class FakeDriveApi extends GoogleDriveApiService
{
    /** @var array<string,list<array<string,mixed>>> */
    public array $listings = [];
    public ?string $failWith = null;
    /** @var list<string> Pastas cuja listagem falha (instabilidade do Google). */
    public array $failFolders = [];
    public int $listCalls = 0;

    public function __construct() {}

    public function isConnected(int $agencyId): bool
    {
        return true;
    }

    public function listFolder(int $agencyId, string $parentDriveId): array
    {
        $this->listCalls++;
        if ($this->failWith !== null) {
            throw new \RuntimeException($this->failWith);
        }
        if (in_array($parentDriveId, $this->failFolders, true)) {
            throw new \RuntimeException(t('drive.list_failed', ['status' => 503]));
        }

        return $this->listings[$parentDriveId] ?? [];
    }
}

/** Dublê de HTTP: o Google só responde 429 (limite de taxa) — nada de rede. */
final class FakeRateLimitedDriveApi extends GoogleDriveApiService
{
    public int $calls = 0;

    public function __construct() {}

    public function isConnected(int $agencyId): bool
    {
        return true;
    }

    public function accessToken(int $agencyId): string
    {
        return 'token-de-teste';
    }

    protected function httpGet(string $url, array $options): \Psr\Http\Message\ResponseInterface
    {
        $this->calls++;

        return new \GuzzleHttp\Psr7\Response(429, ['Content-Type' => 'application/json'], '{"error":{"message":"rate limit"}}');
    }
}
