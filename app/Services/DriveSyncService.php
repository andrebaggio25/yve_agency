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
     * @return array{synced:bool,reason?:string,added?:int,removed?:int,renamed?:int}
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

        $stats = ['added' => 0, 'removed' => 0, 'renamed' => 0];
        $this->reconcileFolder($clientId, $agencyId, null, (string) $client['drive_folder_id'], $stats, 0);

        return ['synced' => true] + $stats;
    }

    /**
     * Sincroniza todos os clientes com pasta no Drive (todas as agências).
     * Usado pelo cron. Erros por cliente não interrompem os demais.
     * @return array{clients:int,added:int,removed:int,renamed:int,errors:int}
     */
    public function syncAll(): array
    {
        $totals = ['clients' => 0, 'added' => 0, 'removed' => 0, 'renamed' => 0, 'errors' => 0];

        foreach ($this->clientRepo->allWithDriveFolder() as $c) {
            $totals['clients']++;
            try {
                $r = $this->syncClient((int) $c['id'], (int) $c['agency_id']);
                if ($r['synced'] ?? false) {
                    $totals['added']   += $r['added']   ?? 0;
                    $totals['removed'] += $r['removed'] ?? 0;
                    $totals['renamed'] += $r['renamed'] ?? 0;
                }
            } catch (\Throwable) {
                $totals['errors']++;
            }
        }

        return $totals;
    }

    /**
     * Reconcilia uma pasta e desce recursivamente nas subpastas.
     * $dbFolderId = null significa a raiz do cliente (folder_id NULL no banco).
     */
    private function reconcileFolder(int $clientId, int $agencyId, ?int $dbFolderId, string $driveFolderId, array &$stats, int $depth): void
    {
        if ($depth > 20) {
            return; // trava de segurança contra recursão anômala
        }

        $remote = $this->driveApi->listFolder($agencyId, $driveFolderId);

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
        $this->reconcileSubfolders($clientId, $agencyId, $dbFolderId, $remoteFolders, $stats, $depth);
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
    private function reconcileSubfolders(int $clientId, int $agencyId, ?int $dbFolderId, array $remoteFolders, array &$stats, int $depth): void
    {
        $dbFolders = $this->folderRepo->children($clientId, $dbFolderId);
        $dbByDriveId = [];
        foreach ($dbFolders as $fo) {
            $dbByDriveId[(string) $fo['drive_folder_id']] = $fo;
        }

        // Sumiu do Drive → remove a subárvore do banco.
        foreach ($dbFolders as $fo) {
            if (!isset($remoteFolders[(string) $fo['drive_folder_id']])) {
                $this->purgeFolderTree($clientId, (int) $fo['id']);
                $stats['removed']++;
            }
        }

        // Novo no Drive → cria e desce; existente → atualiza nome e desce.
        foreach ($remoteFolders as $driveId => $r) {
            $name = $r['name'] ?? 'pasta';

            if (!isset($dbByDriveId[$driveId])) {
                $newId = $this->folderRepo->create([
                    'agency_id'       => $agencyId,
                    'client_id'       => $clientId,
                    'parent_id'       => $dbFolderId,
                    'drive_folder_id' => $driveId,
                    'name'            => $name,
                ]);
                $stats['added']++;
                $this->reconcileFolder($clientId, $agencyId, $newId, $driveId, $stats, $depth + 1);
            } else {
                $existing = $dbByDriveId[$driveId];
                if (($existing['name'] ?? '') !== $name) {
                    $this->folderRepo->updateName((int) $existing['id'], $clientId, $name);
                    $stats['renamed']++;
                }
                $this->reconcileFolder($clientId, $agencyId, (int) $existing['id'], $driveId, $stats, $depth + 1);
            }
        }
    }

    /** Remove do banco a pasta e todos os descendentes (subpastas + arquivos). */
    private function purgeFolderTree(int $clientId, int $folderId): void
    {
        foreach ($this->folderRepo->children($clientId, $folderId) as $child) {
            $this->purgeFolderTree($clientId, (int) $child['id']);
        }
        $this->fileRepo->deleteByFolder($clientId, $folderId);
        $this->folderRepo->deleteForClient($folderId, $clientId);
    }
}
