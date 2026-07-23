<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Repositories\DriveFolderRepository;
use App\Repositories\DriveFileRepository;
use App\Services\DriveUploadService;
use App\Services\GoogleDriveApiService;
use App\Services\DriveSyncService;
use App\Support\Auth;

/**
 * Galeria (lado agência) dos conteúdos do cliente no Drive.
 *
 * Leitura (navegar/preview) + escrita (CONT-06): a equipe cria pastas e envia
 * arquivos pela plataforma — mesma máquina de upload do portal (UP-01, via
 * DriveUploadService), para o editor não precisar mais subir direto no Drive
 * (que o app não enxerga, por causa do escopo drive.file).
 */
class ClientFilesController extends Controller
{
    public function __construct(
        private readonly ClientRepository      $clientRepo,
        private readonly DriveFolderRepository $folderRepo,
        private readonly DriveFileRepository   $fileRepo,
        private readonly GoogleDriveApiService $driveApi,
        private readonly DriveSyncService      $driveSync,
        private readonly DriveUploadService    $uploads,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());
        if (!$client) {
            return Response::view('errors.404', [], 404);
        }

        $connected      = $this->driveApi->isConnected((int) Auth::agencyId());
        $maxUploadBytes = DriveUploadService::maxUploadBytes();

        return $this->view('clients.files', compact('client', 'connected', 'maxUploadBytes'));
    }

    /** JSON: cria subpasta na pasta do cliente (equipe). */
    public function createFolder(Request $request): Response
    {
        $client = $this->requireClient($request);
        if ($client instanceof Response) {
            return $client;
        }

        $name     = trim((string) $request->input('name', ''));
        $parentId = $request->input('parent_id', null);
        $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;

        if ($name === '') {
            return Response::json(['error' => 'Nome obrigatório'], 422);
        }
        if ($parentId !== null && !$this->folderRepo->findForClient($parentId, (int) $client['id'])) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $folder = $this->uploads->createFolder($client, $parentId, $name);

            return Response::json(['success' => true, 'folder' => $folder]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao criar pasta: ' . $e->getMessage()], 500);
        }
    }

    /** JSON: abre a sessão resumável do upload direto browser→Drive (UP-01). */
    public function uploadSession(Request $request): Response
    {
        $client = $this->requireClient($request);
        if ($client instanceof Response) {
            return $client;
        }

        $name = trim((string) $request->input('name', ''));
        $mime = trim((string) $request->input('mime', '')) ?: 'application/octet-stream';
        $size = (int) $request->input('size', 0);

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;

        if ($name === '' || $size <= 0) {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }
        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, (int) $client['id'])) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $uploadUrl = $this->uploads->initiateSession($client, $folderId, $name, $mime, $size);
            if ($uploadUrl === null) {
                return Response::json(['error' => 'Upload direto indisponível'], 422);
            }

            return Response::json(['success' => true, 'upload_url' => $uploadUrl]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao iniciar o envio: ' . $e->getMessage()], 500);
        }
    }

    /** JSON: confirma o upload direto (valida no Drive) e registra o arquivo. */
    public function uploadComplete(Request $request): Response
    {
        $client = $this->requireClient($request);
        if ($client instanceof Response) {
            return $client;
        }

        $driveFileId = trim((string) $request->input('drive_file_id', ''));
        if ($driveFileId === '') {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;
        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, (int) $client['id'])) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $payload = $this->uploads->completeDirect($client, $folderId, $driveFileId, 'panel');
            if ($payload === null) {
                return Response::json(['error' => 'Arquivo não confirmado no Drive.'], 422);
            }

            return Response::json(['success' => true, 'file' => $payload]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao confirmar o envio: ' . $e->getMessage()], 500);
        }
    }

    /** JSON: relay de upload (fallback multipart via PHP — sujeito ao teto do hosting). */
    public function upload(Request $request): Response
    {
        @set_time_limit(0);

        $client = $this->requireClient($request);
        if ($client instanceof Response) {
            return $client;
        }

        $file = $request->file('file');
        $err  = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!$file || $err !== UPLOAD_ERR_OK) {
            $msg = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo maior que o limite do servidor.'
                : 'Falha no envio do arquivo.';
            return Response::json(['error' => $msg], 422);
        }

        $name = trim((string) ($file['name'] ?? 'arquivo'));
        $mime = (string) ($file['type'] ?? 'application/octet-stream');
        $size = (int) ($file['size'] ?? 0);
        $tmp  = (string) ($file['tmp_name'] ?? '');

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;

        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, (int) $client['id'])) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }
        if ($name === '' || $size <= 0 || !is_uploaded_file($tmp)) {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }

        try {
            $payload = $this->uploads->relayUpload($client, $folderId, $name, $mime, $tmp, $size, 'panel');

            return Response::json(['success' => true, 'file' => $payload]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /** Guarda comum dos endpoints de escrita: permissão + cliente da agência. */
    private function requireClient(Request $request): array|Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());
        if (!$client) {
            return Response::json(['error' => 'Cliente não encontrado'], 404);
        }

        return $client;
    }

    public function folders(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());
        if (!$client) {
            return Response::json(['error' => 'Cliente não encontrado'], 404);
        }

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;

        $current    = null;
        $breadcrumb = [];
        if ($folderId !== null) {
            $current = $this->folderRepo->findForClient($folderId, $clientId);
            if (!$current) {
                return Response::json(['error' => 'Pasta não encontrada'], 404);
            }
            $breadcrumb = $this->buildBreadcrumb($current, $clientId);
        }

        return Response::json([
            'success'    => true,
            'breadcrumb' => $breadcrumb,
            'folders'    => array_map(fn($f) => [
                'id'   => (int) $f['id'],
                'name' => $f['name'],
            ], $this->folderRepo->children($clientId, $folderId)),
            'files'      => array_map(fn($f) => [
                'id'            => (int) $f['id'],
                'name'          => $f['name'],
                'mime_type'     => $f['mime_type'] ?? null,
                'size_bytes'    => (int) ($f['size_bytes'] ?? 0),
                'thumbnail'     => $f['thumbnail_link'] ?? null,
                'web_view_link' => $f['web_view_link'] ?? null,
                'drive_file_id' => $f['drive_file_id'] ?? null,
                'is_image'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'image/'),
                'is_video'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'video/'),
            ], $this->fileRepo->forFolder($clientId, $folderId)),
        ]);
    }

    /**
     * Reconcilia a galeria com o Google Drive (sob demanda, via botão).
     * Reflete no sistema o que foi apagado/renomeado direto no Drive.
     */
    public function sync(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $agencyId = (int) Auth::agencyId();
        $client   = $this->clientRepo->findByIdAndAgency($clientId, $agencyId);
        if (!$client) {
            return Response::json(['error' => 'Cliente não encontrado'], 404);
        }

        try {
            $result = $this->driveSync->syncClient($clientId, $agencyId);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => 'Falha ao sincronizar: ' . $e->getMessage()], 500);
        }

        if (!($result['synced'] ?? false)) {
            $reason = $result['reason'] ?? 'unknown';
            $msg = match ($reason) {
                'no_folder'     => 'Este cliente ainda não tem pasta no Drive.',
                'not_connected' => 'Google Drive não está conectado para esta agência.',
                default         => 'Não foi possível sincronizar.',
            };
            return Response::json(['success' => false, 'error' => $msg], 422);
        }

        return Response::json([
            'success' => true,
            'added'   => $result['added']   ?? 0,
            'removed' => $result['removed'] ?? 0,
            'renamed' => $result['renamed'] ?? 0,
        ]);
    }

    /** Proxy de conteúdo (preview/thumbnail) mantendo o arquivo privado. */
    public function raw(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());
        if (!$client) {
            return Response::json(['error' => 'Cliente não encontrado'], 404);
        }

        $fileId = (int) $request->param('fileId');
        $row    = $this->fileRepo->findForClient($fileId, $clientId);
        if (!$row) {
            return Response::json(['error' => 'Arquivo não encontrado'], 404);
        }

        $agencyId = (int) Auth::agencyId();
        $resp     = $this->driveApi->streamResponse($agencyId, $row['drive_file_id'], $request->server('HTTP_RANGE', null));

        // Só mídia passiva vai inline; o resto força download (anti-XSS — o
        // arquivo vem do cliente do portal e é servido no domínio do app).
        $effectiveMime = ($row['mime_type'] ?? null) ?: ($resp->getHeaderLine('Content-Type') ?: null);
        $inline        = \App\Services\GoogleDriveService::inlineSafeMime($effectiveMime);

        http_response_code($resp->getStatusCode());
        foreach (['Content-Length', 'Content-Range', 'Accept-Ranges'] as $h) {
            $v = $resp->getHeaderLine($h);
            if ($v !== '') {
                header("{$h}: {$v}");
            }
        }
        if ($inline) {
            header('Content-Type: ' . ($effectiveMime ?: 'application/octet-stream'));
        } else {
            $safeName = str_replace(['"', "\r", "\n"], '', (string) ($row['name'] ?? '')) ?: 'arquivo';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        $body = $resp->getBody();
        while (!$body->eof()) {
            echo $body->read(65536);
            @ob_flush();
            flush();
        }
        exit;
    }

    private function buildBreadcrumb(array $folder, int $clientId): array
    {
        $chain  = [];
        $cursor = $folder;
        $guard  = 0;
        while ($cursor && $guard < 50) {
            array_unshift($chain, ['id' => (int) $cursor['id'], 'name' => $cursor['name']]);
            $parentId = $cursor['parent_id'] ?? null;
            $cursor = $parentId ? $this->folderRepo->findForClient((int) $parentId, $clientId) : null;
            $guard++;
        }
        return $chain;
    }
}
