<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\PortalAuth;
use App\Repositories\DriveFolderRepository;
use App\Repositories\DriveFileRepository;
use App\Services\DriveUploadService;
use App\Services\GoogleDriveApiService;
use App\Services\GoogleDriveService;

/**
 * Envio de conteúdos pelo portal do cliente (Google Drive).
 *
 * Extraído do PortalController (ARCH-03), que misturava dashboard, planos,
 * feedback e o CRUD inteiro do Drive. Autenticação é o capability-token da URL,
 * resolvido pelo PortalMiddleware — todo método usa `PortalAuth::client()` e
 * escopa por `client_id`/`agency_id` daí, nunca por parâmetro do request.
 *
 * Caminhos de upload (UP-01):
 *   1. direto  — o navegador abre uma sessão resumável e envia os bytes ao
 *                Google; o PHP só valida e registra (sem teto do hosting);
 *   2. relay   — fallback multipart pelo PHP (sujeito ao upload_max_filesize).
 */
class PortalDriveController extends Controller
{
    public function __construct(
        private readonly DriveFolderRepository $folderRepo,
        private readonly DriveFileRepository   $fileRepo,
        private readonly GoogleDriveApiService $driveApi,
        private readonly DriveUploadService    $uploads,
    ) {}

    /** Página "Enviar conteúdos" do portal. */
    public function driveFiles(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();

        $connected      = $this->driveApi->isConnected((int) $client['agency_id']);
        $maxUploadBytes = DriveUploadService::maxUploadBytes();

        return $this->view('portal.files', compact('client', 'token', 'connected', 'maxUploadBytes'));
    }

    /** JSON: lista subpastas + arquivos da pasta atual (folder_id opcional = raiz). */
    public function driveFolders(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];

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
            'folder'     => $current ? ['id' => (int) $current['id'], 'name' => $current['name']] : null,
            'breadcrumb' => $breadcrumb,
            'folders'    => array_map(fn($f) => [
                'id'   => (int) $f['id'],
                'name' => $f['name'],
            ], $this->folderRepo->children($clientId, $folderId)),
            'files'      => array_map(fn($f) => $this->filePayload($f), $this->fileRepo->forFolder($clientId, $folderId)),
        ]);
    }

    /** JSON: cria subpasta. */
    public function driveCreateFolder(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];

        $name     = trim((string) $request->input('name', ''));
        $parentId = $request->input('parent_id', null);
        $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;

        if ($name === '') {
            return Response::json(['error' => 'Nome obrigatório'], 422);
        }
        if ($parentId !== null && !$this->folderRepo->findForClient($parentId, $clientId)) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $folder = $this->uploads->createFolder($client, $parentId, $name);

            return Response::json(['success' => true, 'folder' => $folder]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao criar pasta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Relay de upload: o navegador envia o arquivo (multipart) pra cá e o
     * servidor repassa pro Drive (server-to-server, sem CORS). Grava metadados.
     */
    public function driveUpload(Request $request): Response
    {
        @set_time_limit(0);

        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];

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

        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, $clientId)) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }
        if ($name === '' || $size <= 0 || !is_uploaded_file($tmp)) {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }

        try {
            $payload = $this->uploads->relayUpload($client, $folderId, $name, $mime, $tmp, $size, 'portal');

            return Response::json(['success' => true, 'file' => $payload]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload direto (UP-01): inicia a sessão resumável no Drive e devolve a
     * session URI pro navegador enviar os bytes direto (PUT em chunks, CORS).
     * O PHP sai do caminho dos bytes — o teto de upload do hosting não se aplica.
     * A URI é uma capability de upload atada a esta sessão; não contém o token.
     */
    public function driveUploadSession(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];

        $name = trim((string) $request->input('name', ''));
        $mime = trim((string) $request->input('mime', '')) ?: 'application/octet-stream';
        $size = (int) $request->input('size', 0);

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;

        if ($name === '' || $size <= 0) {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }
        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, $clientId)) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $uploadUrl = $this->uploads->initiateSession($client, $folderId, $name, $mime, $size);
            if ($uploadUrl === null) {
                // Sem APP_URL válida não dá pra vincular o CORS — o front cai no relay.
                return Response::json(['error' => 'Upload direto indisponível'], 422);
            }

            return Response::json(['success' => true, 'upload_url' => $uploadUrl]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao iniciar o envio: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Confirma um upload direto: valida no Drive que o arquivo existe e está na
     * pasta esperada do cliente (não confia no ID vindo do navegador) e então
     * grava os metadados no banco. Idempotente por drive_file_id.
     */
    public function driveUploadComplete(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];

        $driveFileId = trim((string) $request->input('drive_file_id', ''));
        if ($driveFileId === '') {
            return Response::json(['error' => 'Arquivo inválido'], 422);
        }

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;
        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, $clientId)) {
            return Response::json(['error' => 'Pasta não encontrada'], 404);
        }

        try {
            $payload = $this->uploads->completeDirect($client, $folderId, $driveFileId, 'portal');
            if ($payload === null) {
                return Response::json(['error' => 'Arquivo não confirmado no Drive.'], 422);
            }

            return Response::json(['success' => true, 'file' => $payload]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao confirmar o envio: ' . $e->getMessage()], 500);
        }
    }

    /** Exclui um arquivo (Drive + banco). Fallback: se já sumiu do Drive (404), remove do banco mesmo assim. */
    public function driveDeleteFile(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];
        $fileId   = (int) $request->param('fileId');

        $row = $this->fileRepo->findForClient($fileId, $clientId);
        if (!$row) {
            return Response::json(['error' => t('portal.files.not_found')], 404);
        }

        try {
            $this->driveApi->delete($agencyId, (string) $row['drive_file_id']);
        } catch (\Throwable $e) {
            return Response::json(['error' => t('portal.files.delete_failed') . ': ' . $e->getMessage()], 500);
        }

        $this->fileRepo->deleteForClient($fileId, $clientId);

        // Dados para o "Desfazer" instantâneo no portal (o arquivo está na lixeira).
        return Response::json([
            'success' => true,
            'restore' => [
                'drive_file_id'  => $row['drive_file_id'],
                'name'           => $row['name'],
                'mime_type'      => $row['mime_type'] ?? null,
                'size_bytes'     => (int) ($row['size_bytes'] ?? 0),
                'thumbnail_link' => $row['thumbnail_link'] ?? null,
                'web_view_link'  => $row['web_view_link'] ?? null,
                'folder_id'      => $row['folder_id'] !== null ? (int) $row['folder_id'] : null,
            ],
        ]);
    }

    /** Desfaz a exclusão de um arquivo: restaura da lixeira do Drive e recria o registro. */
    public function driveRestoreFile(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];

        $driveFileId = trim((string) $request->input('drive_file_id', ''));
        if ($driveFileId === '') {
            return Response::json(['error' => t('portal.files.not_found')], 422);
        }

        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '') ? null : (int) $folderId;
        if ($folderId !== null && !$this->folderRepo->findForClient($folderId, $clientId)) {
            $folderId = null; // pasta original já não existe → restaura na raiz
        }

        try {
            $this->driveApi->restore($agencyId, $driveFileId);
        } catch (\Throwable $e) {
            return Response::json(['error' => t('portal.files.restore_failed') . ': ' . $e->getMessage()], 500);
        }

        $name = trim((string) $request->input('name', 'arquivo')) ?: 'arquivo';
        $id = $this->fileRepo->create([
            'agency_id'      => $agencyId,
            'client_id'      => $clientId,
            'folder_id'      => $folderId,
            'drive_file_id'  => $driveFileId,
            'name'           => $name,
            'mime_type'      => $request->input('mime_type') ?: null,
            'size_bytes'     => ((int) $request->input('size_bytes', 0)) ?: null,
            'thumbnail_link' => $request->input('thumbnail_link') ?: null,
            'web_view_link'  => $request->input('web_view_link') ?: null,
            'uploaded_via'   => 'portal',
        ]);

        return Response::json([
            'success' => true,
            'file'    => $this->filePayload([
                'id'             => $id,
                'name'           => $name,
                'mime_type'      => $request->input('mime_type'),
                'size_bytes'     => (int) $request->input('size_bytes', 0),
                'thumbnail_link' => $request->input('thumbnail_link'),
                'web_view_link'  => $request->input('web_view_link'),
                'drive_file_id'  => $driveFileId,
            ]),
        ]);
    }

    /** Exclui uma pasta e todo o conteúdo (Drive apaga em cascata; banco limpo recursivamente). */
    public function driveDeleteFolder(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];
        $folderId = (int) $request->param('folderId');

        $folder = $this->folderRepo->findForClient($folderId, $clientId);
        if (!$folder) {
            return Response::json(['error' => t('portal.files.not_found')], 404);
        }

        try {
            // No Drive, excluir a pasta remove o conteúdo junto. 404 = já sumiu (ok).
            $this->driveApi->delete($agencyId, (string) $folder['drive_folder_id']);
        } catch (\Throwable $e) {
            return Response::json(['error' => t('portal.files.delete_failed') . ': ' . $e->getMessage()], 500);
        }

        $this->purgeFolderTree($clientId, $folderId);
        return Response::json(['success' => true]);
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

    /** Proxy de conteúdo (preview/thumbnail) — streama o arquivo do Drive mantendo-o privado. */
    public function driveFileRaw(Request $request): Response
    {
        $client   = PortalAuth::client();
        $clientId = (int) $client['id'];
        $agencyId = (int) $client['agency_id'];
        $fileId   = (int) $request->param('fileId');

        $row = $this->fileRepo->findForClient($fileId, $clientId);
        if (!$row) {
            return Response::json(['error' => t('portal.files.not_found')], 404);
        }

        $resp = $this->driveApi->streamResponse($agencyId, $row['drive_file_id'], $request->server('HTTP_RANGE', null));

        // Órfão: o arquivo foi apagado direto no Drive → limpa o registro e responde 404.
        if ($resp->getStatusCode() === 404) {
            $this->fileRepo->deleteForClient($fileId, $clientId);
            return Response::json(['error' => t('portal.files.gone')], 404);
        }

        return $this->emitStream($resp, $row['mime_type'] ?? null, (string) ($row['name'] ?? 'arquivo'));
    }

    /**
     * Streama uma resposta do Drive direto pra saída (sem bufferizar na memória).
     * Só mídia passiva vai inline; o resto força download — HTML/SVG servidos
     * inline no nosso domínio seriam XSS armazenado (ver inlineSafeMime).
     */
    private function emitStream(\Psr\Http\Message\ResponseInterface $resp, ?string $mime, string $name = 'arquivo'): Response
    {
        $effectiveMime = $mime ?: ($resp->getHeaderLine('Content-Type') ?: null);
        $inline        = GoogleDriveService::inlineSafeMime($effectiveMime);

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
            $safeName = str_replace(['"', "\r", "\n"], '', $name) ?: 'arquivo';
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

    // ── helpers Drive ──────────────────────────────────────────────────────────

    /** Monta o caminho (breadcrumb) subindo pelos parent_id. */
    private function buildBreadcrumb(array $folder, int $clientId): array
    {
        $chain = [];
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

    private function filePayload(array $f): array
    {
        return DriveUploadService::filePayload($f);
    }
}
