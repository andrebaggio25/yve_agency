<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DriveFileRepository;
use App\Repositories\DriveFolderRepository;

/**
 * Caminho de ESCRITA no Drive do cliente (pastas + os dois fluxos de upload
 * do UP-01), compartilhado entre o portal (cliente, via token) e o painel
 * interno (equipe, via sessão) — CONT-06.
 *
 * Quem chama valida o dono (token do portal ou permissão+ClientAccess) e a
 * existência da subpasta; aqui mora a mecânica Drive+banco, idêntica nos dois
 * lados para o arquivo nascer igual (public-por-link, thumbnail, registro em
 * drive_files) venha de onde vier.
 */
class DriveUploadService
{
    public function __construct(
        private readonly DriveFolderRepository $folderRepo,
        private readonly DriveFileRepository   $fileRepo,
        private readonly GoogleDriveApiService $driveApi,
    ) {}

    /**
     * Cria a subpasta no Drive e registra no banco.
     * @return array{id:int,name:string,drive_folder_id:string}
     */
    public function createFolder(array $client, ?int $parentId, string $name): array
    {
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];

        $parentDriveId = $this->resolveParentDriveId($client, $parentId);
        $driveFolderId = $this->driveApi->createFolder($agencyId, $name, $parentDriveId);

        $id = $this->folderRepo->create([
            'agency_id'       => $agencyId,
            'client_id'       => $clientId,
            'parent_id'       => $parentId,
            'drive_folder_id' => $driveFolderId,
            'name'            => $name,
        ]);

        // drive_folder_id volta para o painel montar o "Copiar link da pasta"
        // sem recarregar a galeria. O portal descarta o campo na resposta.
        return ['id' => $id, 'name' => $name, 'drive_folder_id' => $driveFolderId];
    }

    /**
     * Abre a sessão resumável (upload direto browser→Drive) vinculada à origem
     * do app. Retorna a session URI, ou null quando a APP_URL não permite
     * vincular o CORS — o front então cai no relay.
     */
    public function initiateSession(array $client, ?int $folderId, string $name, string $mime, int $size): ?string
    {
        $origin = GoogleDriveApiService::originFromUrl(env('APP_URL', ''));
        if ($origin === null) {
            return null;
        }

        $parentDriveId = $this->resolveParentDriveId($client, $folderId);

        return $this->driveApi->initiateResumable((int) $client['agency_id'], $parentDriveId, $name, $mime, $size, $origin);
    }

    /**
     * Confirma um upload direto: valida no Drive que o arquivo está na pasta
     * esperada do cliente (não confia no ID vindo do navegador) e registra.
     * Idempotente por drive_file_id. Retorna o payload do arquivo, ou null se
     * o arquivo não foi confirmado no Drive.
     */
    public function completeDirect(array $client, ?int $folderId, string $driveFileId, string $via): ?array
    {
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];

        // Repetição do confirm (retry do front) não duplica o registro.
        $existing = $this->fileRepo->findByDriveId($driveFileId, $clientId);
        if ($existing) {
            return self::filePayload($existing);
        }

        $parentDriveId = $this->resolveParentDriveId($client, $folderId);
        $meta          = $this->driveApi->fileMeta($agencyId, $driveFileId);

        if (empty($meta['id']) || !GoogleDriveApiService::metaHasParent($meta, $parentDriveId)) {
            return null;
        }

        // Fonte de verdade dos metadados é o Drive, não o navegador.
        return $this->registerDriveFile(
            $agencyId, $clientId, $folderId, $driveFileId,
            (string) ($meta['name'] ?? 'arquivo'),
            (string) ($meta['mimeType'] ?? 'application/octet-stream'),
            (int) ($meta['size'] ?? 0),
            $meta['thumbnailLink'] ?? null,
            $meta['webViewLink'] ?? null,
            $via
        );
    }

    /**
     * Relay: o servidor repassa o arquivo (já validado pelo caller) pro Drive
     * e registra. Sujeito ao teto de upload do hosting.
     * @return array payload do arquivo
     */
    public function relayUpload(array $client, ?int $folderId, string $name, string $mime, string $tmpPath, int $size, string $via): array
    {
        $agencyId      = (int) $client['agency_id'];
        $parentDriveId = $this->resolveParentDriveId($client, $folderId);
        $uploaded      = $this->driveApi->uploadToFolder($agencyId, $parentDriveId, $name, $mime, $tmpPath, $size);

        return $this->registerDriveFile(
            $agencyId, (int) $client['id'], $folderId,
            (string) $uploaded['id'], $name, $mime, $size,
            $uploaded['thumbnailLink'] ?? null, $uploaded['webViewLink'] ?? null,
            $via
        );
    }

    /**
     * Move em lote arquivos e/ou pastas para outra pasta do cliente (ou raiz,
     * com $targetFolderId null) — Drive e banco. Item que falha não interrompe
     * os demais: vira uma entrada em `errors` e o resto segue.
     *
     * @param list<int> $fileIds
     * @param list<int> $folderIds
     * @return array{moved:int, errors:list<array{type:string,id:int,name:string,error:string}>}
     */
    public function moveItems(array $client, array $fileIds, array $folderIds, ?int $targetFolderId): array
    {
        $clientId = (int) $client['id'];

        // Destino resolvido antes de tocar em qualquer item: se falhar
        // (Drive fora, pasta sumiu), nada foi movido pela metade.
        $targetDriveId = $this->resolveParentDriveId($client, $targetFolderId);

        $moved  = 0;
        $errors = [];

        foreach ($folderIds as $folderId) {
            $folder = $this->folderRepo->findForClient($folderId, $clientId);
            if (!$folder) {
                $errors[] = ['type' => 'folder', 'id' => $folderId, 'name' => '', 'error' => 'Pasta não encontrada.'];
                continue;
            }
            try {
                $this->moveFolderRow($client, $folder, $targetFolderId, $targetDriveId);
                $moved++;
            } catch (\Throwable $e) {
                $errors[] = ['type' => 'folder', 'id' => $folderId, 'name' => (string) $folder['name'], 'error' => $e->getMessage()];
            }
        }

        foreach ($fileIds as $fileId) {
            $file = $this->fileRepo->findForClient($fileId, $clientId);
            if (!$file) {
                $errors[] = ['type' => 'file', 'id' => $fileId, 'name' => '', 'error' => 'Arquivo não encontrado.'];
                continue;
            }
            try {
                $this->moveFileRow($client, $file, $targetFolderId, $targetDriveId);
                $moved++;
            } catch (\Throwable $e) {
                $errors[] = ['type' => 'file', 'id' => $fileId, 'name' => (string) $file['name'], 'error' => $e->getMessage()];
            }
        }

        return ['moved' => $moved, 'errors' => $errors];
    }

    private function moveFileRow(array $client, array $file, ?int $targetFolderId, string $targetDriveId): void
    {
        $currentFolderId = $file['folder_id'] !== null ? (int) $file['folder_id'] : null;
        if ($currentFolderId === $targetFolderId) {
            return; // já está no destino
        }

        $oldDriveId = $this->resolveParentDriveId($client, $currentFolderId);
        $this->driveApi->move((int) $client['agency_id'], (string) $file['drive_file_id'], $targetDriveId, $oldDriveId);
        $this->fileRepo->updateFolder((int) $file['id'], (int) $client['id'], $targetFolderId);
    }

    private function moveFolderRow(array $client, array $folder, ?int $targetFolderId, string $targetDriveId): void
    {
        $folderId = (int) $folder['id'];
        $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;

        if ($parentId === $targetFolderId) {
            return; // já está no destino
        }
        $this->assertTargetOutsideFolder((int) $client['id'], $folderId, $targetFolderId);

        $oldDriveId = $this->resolveParentDriveId($client, $parentId);
        $this->driveApi->move((int) $client['agency_id'], (string) $folder['drive_folder_id'], $targetDriveId, $oldDriveId);
        $this->folderRepo->updateParent($folderId, (int) $client['id'], $targetFolderId);
    }

    /** Impede mover uma pasta para dentro dela mesma ou de um descendente (criaria um ciclo no breadcrumb e na sync). */
    private function assertTargetOutsideFolder(int $clientId, int $folderId, ?int $targetFolderId): void
    {
        $cursor = $targetFolderId;
        $guard  = 0;
        while ($cursor !== null && $guard < 50) {
            if ($cursor === $folderId) {
                throw new \RuntimeException('Não é possível mover uma pasta para dentro dela mesma.');
            }
            $row    = $this->folderRepo->findForClient($cursor, $clientId);
            $cursor = ($row && $row['parent_id'] !== null) ? (int) $row['parent_id'] : null;
            $guard++;
        }
    }

    /**
     * Exclui um arquivo: lixeira do Drive + remoção do registro. Retorna os
     * dados para o "Desfazer" (o arquivo fica recuperável na lixeira), ou
     * null se o arquivo não pertence ao cliente.
     */
    public function deleteFile(array $client, int $fileId): ?array
    {
        $clientId = (int) $client['id'];

        $row = $this->fileRepo->findForClient($fileId, $clientId);
        if (!$row) {
            return null;
        }

        $this->driveApi->delete((int) $client['agency_id'], (string) $row['drive_file_id']);
        $this->fileRepo->deleteForClient($fileId, $clientId);

        return [
            'drive_file_id'  => $row['drive_file_id'],
            'name'           => $row['name'],
            'mime_type'      => $row['mime_type'] ?? null,
            'size_bytes'     => (int) ($row['size_bytes'] ?? 0),
            'thumbnail_link' => $row['thumbnail_link'] ?? null,
            'web_view_link'  => $row['web_view_link'] ?? null,
            'folder_id'      => $row['folder_id'] !== null ? (int) $row['folder_id'] : null,
        ];
    }

    /**
     * Desfaz a exclusão: restaura da lixeira do Drive e recria o registro.
     * Se a pasta original já não existe, o arquivo volta para a raiz.
     * @param array $meta name/mime_type/size_bytes/thumbnail_link/web_view_link (do payload do "Desfazer")
     * @return array payload do arquivo restaurado
     */
    public function restoreFile(array $client, string $driveFileId, ?int $folderId, array $meta, string $via): array
    {
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];

        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, $clientId)) {
            $folderId = null;
        }

        $this->driveApi->restore($agencyId, $driveFileId);

        $name = trim((string) ($meta['name'] ?? '')) ?: 'arquivo';
        $id   = $this->fileRepo->create([
            'agency_id'      => $agencyId,
            'client_id'      => $clientId,
            'folder_id'      => $folderId,
            'drive_file_id'  => $driveFileId,
            'name'           => $name,
            'mime_type'      => ($meta['mime_type'] ?? null) ?: null,
            'size_bytes'     => ((int) ($meta['size_bytes'] ?? 0)) ?: null,
            'thumbnail_link' => ($meta['thumbnail_link'] ?? null) ?: null,
            'web_view_link'  => ($meta['web_view_link'] ?? null) ?: null,
            'uploaded_via'   => $via,
        ]);

        return self::filePayload([
            'id'             => $id,
            'name'           => $name,
            'mime_type'      => $meta['mime_type'] ?? null,
            'size_bytes'     => (int) ($meta['size_bytes'] ?? 0),
            'thumbnail_link' => $meta['thumbnail_link'] ?? null,
            'web_view_link'  => $meta['web_view_link'] ?? null,
            'drive_file_id'  => $driveFileId,
        ]);
    }

    /**
     * Exclui uma pasta e todo o conteúdo (o Drive apaga em cascata na lixeira;
     * o banco é limpo recursivamente). False se a pasta não é do cliente.
     */
    public function deleteFolder(array $client, int $folderId): bool
    {
        $clientId = (int) $client['id'];

        $folder = $this->folderRepo->findForClient($folderId, $clientId);
        if (!$folder) {
            return false;
        }

        $this->driveApi->delete((int) $client['agency_id'], (string) $folder['drive_folder_id']);
        $this->purgeFolderTree($clientId, $folderId);

        return true;
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

    /** Renomeia um arquivo (Drive + banco). Retorna o payload atualizado, ou null se não é do cliente. */
    public function renameFile(array $client, int $fileId, string $name): ?array
    {
        $clientId = (int) $client['id'];

        $row = $this->fileRepo->findForClient($fileId, $clientId);
        if (!$row) {
            return null;
        }

        $this->driveApi->rename((int) $client['agency_id'], (string) $row['drive_file_id'], $name);
        $this->fileRepo->updateName($fileId, $clientId, $name);

        return self::filePayload(array_merge($row, ['name' => $name]));
    }

    /** Renomeia uma pasta (Drive + banco). Retorna {id,name}, ou null se não é do cliente. */
    public function renameFolder(array $client, int $folderId, string $name): ?array
    {
        $clientId = (int) $client['id'];

        $row = $this->folderRepo->findForClient($folderId, $clientId);
        if (!$row) {
            return null;
        }

        $this->driveApi->rename((int) $client['agency_id'], (string) $row['drive_folder_id'], $name);
        $this->folderRepo->updateName($folderId, $clientId, $name);

        return ['id' => (int) $row['id'], 'name' => $name];
    }

    /** Normaliza a lista de IDs vinda do navegador (só inteiros positivos, sem repetição). @return list<int> */
    public static function idList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = array_map('intval', array_filter($raw, 'is_numeric'));

        return array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
    }

    /** Resolve o ID da pasta-pai no Drive (raiz do cliente, criada sob demanda, ou subpasta). */
    public function resolveParentDriveId(array $client, ?int $folderId): string
    {
        if ($folderId !== null) {
            $folder = $this->folderRepo->findForClient($folderId, (int) $client['id']);
            if (!$folder) {
                throw new \RuntimeException('Pasta não encontrada.');
            }
            return $folder['drive_folder_id'];
        }

        return $this->driveApi->ensureClientFolder($client, (int) $client['agency_id']);
    }

    /** Maior arquivo que o servidor aceita por upload via relay (min de upload_max_filesize/post_max_size). 0 = sem limite conhecido. */
    public static function maxUploadBytes(): int
    {
        $toBytes = static function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $num  = (int) $v;
            $unit = strtolower(substr($v, -1));
            return match ($unit) {
                'g'     => $num * 1024 * 1024 * 1024,
                'm'     => $num * 1024 * 1024,
                'k'     => $num * 1024,
                default => (int) $v,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ], static fn (int $x): bool => $x > 0);

        return $limits ? (int) min($limits) : 0;
    }

    /** Formato único de arquivo para o JSON das telas (portal e painel). */
    public static function filePayload(array $f): array
    {
        return [
            'id'            => (int) ($f['id'] ?? 0),
            'name'          => $f['name'] ?? '',
            'mime_type'     => $f['mime_type'] ?? null,
            'size_bytes'    => (int) ($f['size_bytes'] ?? 0),
            'thumbnail'     => $f['thumbnail_link'] ?? null,
            'web_view_link' => $f['web_view_link'] ?? null,
            'drive_file_id' => $f['drive_file_id'] ?? null,
            'is_image'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'image/'),
            'is_video'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'video/'),
        ];
    }

    /**
     * Passo comum pós-upload: permissão de preview best-effort, completa
     * thumbnail/link se faltarem, grava em drive_files e monta o payload.
     */
    private function registerDriveFile(
        int $agencyId,
        int $clientId,
        ?int $folderId,
        string $driveFileId,
        string $name,
        string $mime,
        int $size,
        ?string $thumb,
        ?string $webView,
        string $via,
    ): array {
        // Torna público-por-link pra habilitar o preview nativo do Google (best-effort).
        try {
            $this->driveApi->setAnyoneReader($agencyId, $driveFileId);
        } catch (\Throwable) {
            // segue mesmo se a permissão falhar — o proxy ainda funciona
        }

        if ($thumb === null || $webView === null) {
            try {
                $meta    = $this->driveApi->fileMeta($agencyId, $driveFileId);
                $thumb   = $thumb   ?? ($meta['thumbnailLink'] ?? null);
                $webView = $webView ?? ($meta['webViewLink'] ?? null);
            } catch (\Throwable) {
                // segue sem metadados extras
            }
        }

        $id = $this->fileRepo->create([
            'agency_id'      => $agencyId,
            'client_id'      => $clientId,
            'folder_id'      => $folderId,
            'drive_file_id'  => $driveFileId,
            'name'           => $name,
            'mime_type'      => $mime ?: null,
            'size_bytes'     => $size > 0 ? $size : null,
            'thumbnail_link' => $thumb,
            'web_view_link'  => $webView,
            'uploaded_via'   => $via,
        ]);

        return self::filePayload([
            'id'             => $id,
            'name'           => $name,
            'mime_type'      => $mime,
            'size_bytes'     => $size,
            'thumbnail_link' => $thumb,
            'web_view_link'  => $webView,
            'drive_file_id'  => $driveFileId,
        ]);
    }
}
