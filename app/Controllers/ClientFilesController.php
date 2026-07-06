<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Repositories\DriveFolderRepository;
use App\Repositories\DriveFileRepository;
use App\Services\GoogleDriveApiService;
use App\Services\DriveSyncService;
use App\Support\Auth;

/**
 * Galeria (lado agência) dos conteúdos enviados pelos clientes via portal.
 * Somente leitura: navega pastas, visualiza thumbnails, abre/baixa do Drive.
 */
class ClientFilesController extends Controller
{
    public function __construct(
        private readonly ClientRepository      $clientRepo,
        private readonly DriveFolderRepository $folderRepo,
        private readonly DriveFileRepository   $fileRepo,
        private readonly GoogleDriveApiService $driveApi,
        private readonly DriveSyncService      $driveSync,
    ) {}

    public function index(Request $request): Response
    {
        Auth::requirePermission('clients.view');

        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());
        if (!$client) {
            return Response::view('errors.404', [], 404);
        }

        return $this->view('clients.files', compact('client'));
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

        http_response_code($resp->getStatusCode());
        foreach (['Content-Type', 'Content-Length', 'Content-Range', 'Accept-Ranges'] as $h) {
            $v = $resp->getHeaderLine($h);
            if ($v !== '') {
                header("{$h}: {$v}");
            }
        }
        if ($resp->getHeaderLine('Content-Type') === '' && !empty($row['mime_type'])) {
            header('Content-Type: ' . $row['mime_type']);
        }
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
