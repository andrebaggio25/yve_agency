<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Repositories\DriveFileRepository;
use App\Repositories\DriveFolderRepository;

/**
 * Reconcilia a árvore de Drive de um cliente (pastas + arquivos) com o banco.
 *
 * Origem da verdade: o Google Drive. Se algo foi apagado/renomeado direto no
 * Drive, o banco passa a refletir. Insere itens do Drive que faltam no banco,
 * remove os que sumiram e atualiza nomes/metadados.
 *
 * RESSALVA IMPORTANTE (escopo drive.file): a API só enxerga arquivos que o
 * próprio app criou. Arquivos adicionados MANUALMENTE na interface do Drive são
 * invisíveis aqui e NÃO aparecerão — isso exigiria escopo drive.readonly +
 * verificação do Google (ver DRIVE-02 no PLANO_MESTRE).
 */
class DriveSyncService
{
    public function __construct(
        private readonly GoogleDriveApiService $driveApi,
        private readonly DriveFolderRepository $folderRepo,
        private readonly DriveFileRepository   $fileRepo,
        private readonly ClientRepository      $clientRepo,
    ) {}

    /**
     * Sincroniza um cliente. Retorna contadores do que mudou.
     *
     * `reason = 'remote_empty'` é a trava do BUG-03: alguma pasta voltou vazia
     * do Drive com conteúdo no banco. A pasta suspeita é **ignorada** (nada é
     * apagado nela) e o resto da galeria segue sincronizando — travar o cliente
     * inteiro deixava o botão Sincronizar inútil enquanto o registro fantasma
     * existisse. `skipped` diz quantas pastas ficaram de fora e
     * `skipped_folders` o caminho de cada uma (o usuário precisa saber ONDE
     * olhar); `removed` conta linhas apagadas, não pastas.
     *
     * `reason = 'list_failed'` é falha do Google na listagem. A reconciliação é
     * incremental e sem transação: o que já foi removido antes da falha FICA
     * removido, então os contadores voltam junto com o erro em vez de morrer
     * com a exceção — dizer "nada mudou" seria mentira.
     *
     * @return array{synced:bool,reason?:string,error?:string,added?:int,removed?:int,renamed?:int,skipped?:int,skipped_folders?:list<string>}
     */
    public function syncClient(int $clientId, int $agencyId): array
    {
        $client = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client || empty($client['drive_folder_id'])) {
            return ['synced' => false, 'reason' => 'no_folder'];
        }
        if (!$this->driveApi->isConnected($agencyId)) {
            return ['synced' => false, 'reason' => 'not_connected'];
        }

        $stats = ['added' => 0, 'removed' => 0, 'renamed' => 0, 'skipped' => 0, 'skipped_folders' => []];
        $error = null;
        try {
            $this->reconcileFolder($clientId, $agencyId, null, (string) $client['drive_folder_id'], $stats, 0, '/');
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $counts = [
            'added'           => $stats['added'],
            'removed'         => $stats['removed'],
            'renamed'         => $stats['renamed'],
            'skipped'         => $stats['skipped'],
            'skipped_folders' => $stats['skipped_folders'],
        ];

        if ($error !== null) {
            return ['synced' => false, 'reason' => 'list_failed', 'error' => $error] + $counts;
        }

        if ($stats['skipped'] > 0) {
            return ['synced' => false, 'reason' => 'remote_empty'] + $counts;
        }

        return ['synced' => true] + $counts;
    }

    /**
     * Sincroniza todos os clientes com pasta no Drive (todas as agências).
     * Usado pelo cron. Erros por cliente não interrompem os demais.
     *
     * `skipped` conta as pastas em que a trava do BUG-03 pegou (vazia no Drive
     * com conteúdo no banco) e `errors` os clientes que falharam — o cron
     * reporta os dois em vez de esvaziar a galeria em silêncio. `removed` é em
     * linhas de `drive_files`/`drive_folders`, para a perda em massa aparecer.
     *
     * Cliente que falhou no meio conta em `errors` mas o que ele já aplicou
     * entra nos totais: a varredura não é transacional, então zerar os números
     * do cliente que deu 429 esconderia remoção já gravada. `skipped_folders`
     * e `failed` dizem em qual cliente/pasta olhar (`#id` do cliente).
     *
     * @return array{clients:int,added:int,removed:int,renamed:int,errors:int,skipped:int,skipped_folders:list<string>,failed:list<string>}
     */
    public function syncAll(): array
    {
        $totals = ['clients' => 0, 'added' => 0, 'removed' => 0, 'renamed' => 0, 'errors' => 0, 'skipped' => 0];
        /** @var list<string> $skippedFolders */
        $skippedFolders = [];
        /** @var list<string> $failed */
        $failed = [];

        foreach ($this->clientRepo->allWithDriveFolder() as $c) {
            $totals['clients']++;
            $ref = '#' . (int) $c['id'];
            try {
                $r = $this->syncClient((int) $c['id'], (int) $c['agency_id']);
                $totals['added']   += $r['added']   ?? 0;
                $totals['removed'] += $r['removed'] ?? 0;
                $totals['renamed'] += $r['renamed'] ?? 0;
                $totals['skipped'] += $r['skipped'] ?? 0;
                foreach ($r['skipped_folders'] ?? [] as $path) {
                    $skippedFolders[] = $ref . ' ' . $path;
                }
                if (($r['reason'] ?? '') === 'list_failed') {
                    $totals['errors']++;
                    $failed[] = $ref . ' ' . ($r['error'] ?? '');
                }
            } catch (\Throwable $e) {
                $totals['errors']++;
                $failed[] = $ref . ' ' . $e->getMessage();
            }
        }

        return $totals + ['skipped_folders' => $skippedFolders, 'failed' => $failed];
    }

    /**
     * Reconcilia uma pasta e desce recursivamente nas subpastas.
     * $dbFolderId = null significa a raiz do cliente (folder_id NULL no banco).
     * $path é o caminho legível da pasta ('/' na raiz), só para o relatório.
     */
    private function reconcileFolder(int $clientId, int $agencyId, ?int $dbFolderId, string $driveFolderId, array &$stats, int $depth, string $path): void
    {
        if ($depth > 20) {
            return; // trava contra recursão anômala
        }

        $remote = $this->driveApi->listFolder($agencyId, $driveFolderId);

        // Trava do BUG-03: pasta vazia no Drive com conteúdo no banco é
        // suspeita (token de outra conta Google, escopo drive.file perdido,
        // resposta parcial). Reconciliar aqui apagaria a galeria do cliente —
        // melhor pular ESTA pasta e reportar do que apagar na dúvida. As outras
        // seguem normalmente: parar o cliente inteiro travava o Sincronizar
        // enquanto o registro fantasma existisse. O caminho vai no relatório:
        // sem ele o usuário não sabe em qual pasta conferir (e a causa legítima
        // é alguém ter apagado os arquivos direto no Drive).
        if ($remote === [] && $this->hasLocalContent($clientId, $dbFolderId)) {
            $stats['skipped']++;
            $stats['skipped_folders'][] = $path;
            return;
        }

        $remoteFolders = [];
        $remoteFiles   = [];
        foreach ($remote as $item) {
            $id = $item['id'] ?? null;
            if ($id === null) {
                continue;
            }
            if ($this->driveApi->isFolderMime($item['mimeType'] ?? null)) {
                $remoteFolders[$id] = $item;
            } else {
                $remoteFiles[$id] = $item;
            }
        }

        $this->reconcileFiles($clientId, $agencyId, $dbFolderId, $remoteFiles, $stats);
        $this->reconcileSubfolders($clientId, $agencyId, $dbFolderId, $remoteFolders, $stats, $depth, $path);
    }

    /** @param array<string,array> $remoteFiles indexado por drive_file_id */
    private function reconcileFiles(int $clientId, int $agencyId, ?int $dbFolderId, array $remoteFiles, array &$stats): void
    {
        $dbFiles = $this->fileRepo->forFolder($clientId, $dbFolderId);
        $dbByDriveId = [];
        foreach ($dbFiles as $f) {
            $dbByDriveId[(string) $f['drive_file_id']] = $f;
        }

        // Sumiu do Drive → remove do banco.
        foreach ($dbFiles as $f) {
            if (!isset($remoteFiles[(string) $f['drive_file_id']])) {
                $this->fileRepo->deleteForClient((int) $f['id'], $clientId);
                $stats['removed']++;
            }
        }

        // Novo no Drive → insere; nome mudou → atualiza.
        foreach ($remoteFiles as $driveId => $r) {
            $payload = [
                'name'           => $r['name'] ?? 'arquivo',
                'mime_type'      => $r['mimeType'] ?? null,
                'size_bytes'     => isset($r['size']) ? (int) $r['size'] : null,
                'thumbnail_link' => $r['thumbnailLink'] ?? null,
                'web_view_link'  => $r['webViewLink'] ?? null,
            ];

            if (!isset($dbByDriveId[$driveId])) {
                $this->fileRepo->create($payload + [
                    'agency_id'     => $agencyId,
                    'client_id'     => $clientId,
                    'folder_id'     => $dbFolderId,
                    'drive_file_id' => $driveId,
                    'uploaded_via'  => 'drive',
                ]);
                $stats['added']++;
            } elseif (($dbByDriveId[$driveId]['name'] ?? '') !== $payload['name']) {
                $this->fileRepo->updateFromDrive((int) $dbByDriveId[$driveId]['id'], $clientId, $payload);
                $stats['renamed']++;
            }
        }
    }

    /** @param array<string,array> $remoteFolders indexado por drive_folder_id */
    private function reconcileSubfolders(int $clientId, int $agencyId, ?int $dbFolderId, array $remoteFolders, array &$stats, int $depth, string $path): void
    {
        $dbFolders = $this->folderRepo->children($clientId, $dbFolderId);
        $dbByDriveId = [];
        foreach ($dbFolders as $fo) {
            $dbByDriveId[(string) $fo['drive_folder_id']] = $fo;
        }

        // Sumiu do Drive → remove a subárvore do banco. `removed` soma as linhas
        // que a purga apagou (pasta + descendentes), não "1 pasta": é por aqui
        // que a perda em massa aparece no relatório do sync.
        foreach ($dbFolders as $fo) {
            if (!isset($remoteFolders[(string) $fo['drive_folder_id']])) {
                $stats['removed'] += $this->purgeFolderTree($clientId, (int) $fo['id']);
            }
        }

        // Novo no Drive → cria e desce; existente → atualiza nome e desce.
        foreach ($remoteFolders as $driveId => $r) {
            $name     = $r['name'] ?? 'pasta';
            $subPath  = rtrim($path, '/') . '/' . $name;

            if (!isset($dbByDriveId[$driveId])) {
                $newId = $this->folderRepo->create([
                    'agency_id'       => $agencyId,
                    'client_id'       => $clientId,
                    'parent_id'       => $dbFolderId,
                    'drive_folder_id' => $driveId,
                    'name'            => $name,
                ]);
                $stats['added']++;
                $this->reconcileFolder($clientId, $agencyId, $newId, $driveId, $stats, $depth + 1, $subPath);
            } else {
                $existing = $dbByDriveId[$driveId];
                if (($existing['name'] ?? '') !== $name) {
                    $this->folderRepo->updateName((int) $existing['id'], $clientId, $name);
                    $stats['renamed']++;
                }
                $this->reconcileFolder($clientId, $agencyId, (int) $existing['id'], $driveId, $stats, $depth + 1, $subPath);
            }
        }
    }

    /** True se o banco tem arquivo ou subpasta nesta pasta do cliente. */
    private function hasLocalContent(int $clientId, ?int $dbFolderId): bool
    {
        return $this->fileRepo->forFolder($clientId, $dbFolderId) !== []
            || $this->folderRepo->children($clientId, $dbFolderId) !== [];
    }

    /**
     * Remove do banco a pasta e todos os descendentes (subpastas + arquivos).
     * Devolve quantas linhas apagou, para o sync reportar o tamanho da perda.
     */
    private function purgeFolderTree(int $clientId, int $folderId): int
    {
        $rows = 0;
        foreach ($this->folderRepo->children($clientId, $folderId) as $child) {
            $rows += $this->purgeFolderTree($clientId, (int) $child['id']);
        }
        $rows += $this->fileRepo->deleteByFolder($clientId, $folderId);
        $this->folderRepo->deleteForClient($folderId, $clientId);

        return $rows + 1; // +1 = a própria pasta
    }
}
