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
     * @return array{id:int,name:string}
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

        return ['id' => $id, 'name' => $name];
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
