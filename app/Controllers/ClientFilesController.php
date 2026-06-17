<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Repositories\DriveFolderRepository;
use App\Repositories\DriveFileRepository;
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
                'is_image'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'image/'),
                'is_video'      => str_starts_with((string) ($f['mime_type'] ?? ''), 'video/'),
            ], $this->fileRepo->forFolder($clientId, $folderId)),
        ]);
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
