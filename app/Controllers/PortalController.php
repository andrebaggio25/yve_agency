<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\PortalAuth;
use App\Repositories\ClientRepository;
use App\Repositories\ContentPlanRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\ContractRepository;
use App\Repositories\AdAccountRepository;
use App\Repositories\AdMetricsRepository;
use App\Repositories\OrganicAccountRepository;
use App\Repositories\OrganicMetricsRepository;
use App\Repositories\DriveFolderRepository;
use App\Repositories\DriveFileRepository;
use App\Services\ContentPlanService;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveApiService;

class PortalController extends Controller
{
    public function __construct(
        private readonly ClientRepository        $clientRepo,
        private readonly ContentPlanRepository   $planRepo,
        private readonly InvoiceRepository       $invoiceRepo,
        private readonly ContractRepository      $contractRepo,
        private readonly AdAccountRepository     $adAccountRepo,
        private readonly AdMetricsRepository     $adMetricsRepo,
        private readonly OrganicAccountRepository   $organicAccountRepo,
        private readonly OrganicMetricsRepository   $organicMetricsRepo,
        private readonly ContentPlanService      $planService,
        private readonly GoogleDriveService      $drive,
        private readonly DriveFolderRepository   $folderRepo,
        private readonly DriveFileRepository     $fileRepo,
        private readonly GoogleDriveApiService   $driveApi,
    ) {}

    // Returns true if status badge should change color
    private static function videoTypes(): array
    {
        return ['Reels / Vídeo', 'reels', 'Story'];
    }

    // ---------------------------------------------------------------- dashboard

    public function index(Request $request): Response
    {
        $client  = PortalAuth::client();
        $token   = PortalAuth::token();

        $clientId  = (int) $client['id'];
        $agencyId  = (int) $client['agency_id'];

        $plans    = $this->planRepo->allByClient($clientId, $agencyId);
        $invoices = $this->invoiceRepo->findByClient($clientId);

        // Ads metrics for this client (last 30 days)
        $since = date('Y-m-d', strtotime('-30 days'));
        $until = date('Y-m-d');
        $adAccounts   = $this->adAccountRepo->findByClient($clientId, $agencyId);
        $adsSummary   = [];
        foreach ($adAccounts as $acc) {
            $s = $this->adMetricsRepo->summaryForAccount((int) $acc['id'], $since, $until);
            foreach ($s as $k => $v) {
                $adsSummary[$k] = ($adsSummary[$k] ?? 0) + (float) $v;
            }
        }

        // Organic metrics for this client (last 30 days)
        $organicAccounts = $this->organicAccountRepo->findByClient($clientId, $agencyId);
        $organicSummary  = [];
        foreach ($organicAccounts as $acc) {
            $s = $this->organicMetricsRepo->summaryForAccount((int) $acc['id'], $since, $until);
            foreach ($s as $k => $v) {
                $organicSummary[$k] = ($organicSummary[$k] ?? 0) + (float) $v;
            }
        }

        $stats = [
            'plans_pending'  => count(array_filter($plans,    fn($p) => $p['status'] === 'pending_approval')),
            'plans_approved' => count(array_filter($plans,    fn($p) => $p['status'] === 'approved')),
            'invoices_open'  => count(array_filter($invoices, fn($i) => $i['status'] === 'sent')),
            'invoices_paid'  => count(array_filter($invoices, fn($i) => $i['status'] === 'paid')),
        ];

        return $this->view('portal.index', compact(
            'client', 'token', 'plans', 'invoices', 'stats',
            'adsSummary', 'organicSummary', 'since', 'until'
        ));
    }

    // ---------------------------------------------------------- content plans

    public function plans(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $plans  = $this->planRepo->allByClient((int) $client['id'], (int) $client['agency_id']);

        return $this->view('portal.plans', compact('client', 'token', 'plans'));
    }

    public function planShow(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan  = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        $items = $this->planRepo->getItems($planId);
        foreach ($items as &$item) {
            $item['drive_parsed'] = !empty($item['drive_url']) ? $this->drive->parse($item['drive_url']) : null;
            $item['feedbacks']    = $this->planRepo->getFeedbacks((int) $item['id']);
            $item['images_list']  = is_string($item['images'] ?? null) ? (json_decode($item['images'], true) ?? []) : ($item['images'] ?? []);
        }
        unset($item);

        return $this->view('portal.plan_show', compact('client', 'token', 'plan', 'items'));
    }

    public function planApprove(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        if ($plan['status'] === 'pending_approval') {
            $this->planService->approvePlan($planId, (int) $client['id']);
        }

        $this->withSuccess('Plano aprovado!');
        return $this->redirect("/portal/{$token}/planos/{$planId}");
    }

    public function planRevision(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();
        $planId = (int) $request->param('planId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::view('errors.404', [], 404);
        }

        $comment = trim((string) $request->post('comment', ''));
        if ($plan['status'] === 'pending_approval') {
            $this->planService->requestRevision($planId, (int) $client['id'], $comment);
        }

        $this->withSuccess('Revisão solicitada.');
        return $this->redirect("/portal/{$token}/planos/{$planId}");
    }

    public function itemFeedback(Request $request): Response
    {
        $client  = PortalAuth::client();
        $planId  = (int) $request->param('planId');
        $itemId  = (int) $request->param('itemId');

        $plan = $this->planRepo->findByIdForClient($planId, (int) $client['id']);
        if (!$plan) {
            return Response::json(['error' => 'Plano não encontrado'], 404);
        }

        $item = $this->planRepo->findItemForClient($itemId, (int) $client['id']);
        if (!$item || (int) $item['content_plan_id'] !== $planId) {
            return Response::json(['error' => 'Item não encontrado'], 404);
        }

        $type    = $request->input('feedback_type', 'comment');
        $comment = trim((string) $request->input('comment', ''));
        $tcRaw   = $request->input('timecode', '');

        $allowed = ['approved', 'changes_requested', 'comment'];
        if (!in_array($type, $allowed, true)) {
            return Response::json(['error' => 'Tipo inválido'], 422);
        }

        // Parse timecode "MM:SS" → seconds
        $timecodeSeconds = null;
        if ($tcRaw !== '' && preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $tcRaw), $m)) {
            $timecodeSeconds = (int)$m[1] * 60 + (int)$m[2];
        }

        $feedbackId = $this->planService->addFeedback(
            $itemId, $planId, (int) $client['id'], null,
            $type, $comment ?: null, $timecodeSeconds, 'client'
        );

        $author   = $client['name'] ?? 'Cliente';
        $timecode = $timecodeSeconds !== null
            ? sprintf('%d:%02d', intdiv($timecodeSeconds, 60), $timecodeSeconds % 60)
            : null;

        $typeLabels = [
            'approved'          => 'Aprovado',
            'changes_requested' => 'Alteração solicitada',
            'comment'           => 'Comentário',
        ];

        return Response::json([
            'success'  => true,
            'feedback' => [
                'id'            => $feedbackId,
                'feedback_type' => $type,
                'type_label'    => $typeLabels[$type],
                'comment'       => $comment ?: null,
                'client_name'   => $author,
                'user_name'     => null,
                'source'        => 'client',
                'timecode'      => $timecode,
                'created_at'    => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    // -------------------------------------------------- envio de conteúdos (Drive)

    /** Página "Enviar conteúdos" do portal. */
    public function driveFiles(Request $request): Response
    {
        $client = PortalAuth::client();
        $token  = PortalAuth::token();

        $connected = $this->driveApi->isConnected((int) $client['agency_id']);

        return $this->view('portal.files', compact('client', 'token', 'connected'));
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
        $agencyId = (int) $client['agency_id'];

        $name     = trim((string) $request->input('name', ''));
        $parentId = $request->input('parent_id', null);
        $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;

        if ($name === '') {
            return Response::json(['error' => 'Nome obrigatório'], 422);
        }

        try {
            $parentDriveId = $this->resolveParentDriveId($client, $clientId, $agencyId, $parentId);
            $driveFolderId = $this->driveApi->createFolder($agencyId, $name, $parentDriveId);

            $id = $this->folderRepo->create([
                'agency_id'       => $agencyId,
                'client_id'       => $clientId,
                'parent_id'       => $parentId,
                'drive_folder_id' => $driveFolderId,
                'name'            => $name,
            ]);

            return Response::json([
                'success' => true,
                'folder'  => ['id' => $id, 'name' => $name],
            ]);
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
        $agencyId = (int) $client['agency_id'];

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
            $parentDriveId = $this->resolveParentDriveId($client, $clientId, $agencyId, $folderId);
            $uploaded      = $this->driveApi->uploadToFolder($agencyId, $parentDriveId, $name, $mime, $tmp, $size);

            // Torna público-por-link pra habilitar o preview nativo do Google (best-effort).
            try {
                $this->driveApi->setAnyoneReader($agencyId, $uploaded['id']);
            } catch (\Throwable) {
                // segue mesmo se a permissão falhar — o proxy ainda funciona
            }

            $thumb = $uploaded['thumbnailLink'] ?? null;
            $webView = $uploaded['webViewLink'] ?? null;
            if ($thumb === null || $webView === null) {
                try {
                    $meta    = $this->driveApi->fileMeta($agencyId, $uploaded['id']);
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
                'drive_file_id'  => $uploaded['id'],
                'name'           => $name,
                'mime_type'      => $mime ?: null,
                'size_bytes'     => $size ?: null,
                'thumbnail_link' => $thumb,
                'web_view_link'  => $webView,
                'uploaded_via'   => 'portal',
            ]);

            return Response::json([
                'success' => true,
                'file'    => $this->filePayload([
                    'id'             => $id,
                    'name'           => $name,
                    'mime_type'      => $mime,
                    'size_bytes'     => $size,
                    'thumbnail_link' => $thumb,
                    'web_view_link'  => $webView,
                    'drive_file_id'  => $uploaded['id'],
                ]),
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Falha ao enviar: ' . $e->getMessage()], 500);
        }
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
            return Response::json(['error' => 'Arquivo não encontrado'], 404);
        }

        return $this->streamDriveFile($agencyId, $row['drive_file_id'], $row['mime_type'] ?? null, $request->server('HTTP_RANGE', null));
    }

    /** Streama um arquivo do Drive direto pra saída (sem bufferizar na memória). */
    private function streamDriveFile(int $agencyId, string $driveFileId, ?string $mime, ?string $range): Response
    {
        $resp = $this->driveApi->streamResponse($agencyId, $driveFileId, $range);

        http_response_code($resp->getStatusCode());
        foreach (['Content-Type', 'Content-Length', 'Content-Range', 'Accept-Ranges'] as $h) {
            $v = $resp->getHeaderLine($h);
            if ($v !== '') {
                header("{$h}: {$v}");
            }
        }
        if ($resp->getHeaderLine('Content-Type') === '' && $mime) {
            header('Content-Type: ' . $mime);
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

    // ── helpers Drive ──────────────────────────────────────────────────────────

    /** Resolve o ID da pasta no Drive que será o pai (raiz do cliente ou subpasta). */
    private function resolveParentDriveId(array $client, int $clientId, int $agencyId, ?int $folderId): string
    {
        if ($folderId !== null) {
            $folder = $this->folderRepo->findForClient($folderId, $clientId);
            if (!$folder) {
                throw new \RuntimeException('Pasta não encontrada.');
            }
            return $folder['drive_folder_id'];
        }

        // Raiz do cliente (cria sob demanda).
        return $this->driveApi->ensureClientFolder($client, $agencyId);
    }

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

    // ------------------------------------------------------------ invoices

    public function invoices(Request $request): Response
    {
        $client   = PortalAuth::client();
        $token    = PortalAuth::token();
        $invoices = $this->invoiceRepo->findByClient((int) $client['id']);

        return $this->view('portal.invoices', compact('client', 'token', 'invoices'));
    }

    // ------------------------------------------------------------ contracts

    public function contracts(Request $request): Response
    {
        $client    = PortalAuth::client();
        $token     = PortalAuth::token();
        $contracts = $this->contractRepo->findByClient((int) $client['id']);

        return $this->view('portal.contracts', compact('client', 'token', 'contracts'));
    }

    // ------------------------------------------------ agency admin: manage portal

    public function adminRegenerateToken(Request $request): Response
    {
        Auth::requirePermission('clients.view');
        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());

        if (!$client) return Response::view('errors.404', [], 404);

        $this->clientRepo->regeneratePortalToken($clientId);
        $this->withSuccess('Link do portal regenerado.');
        return $this->redirect('/clientes/' . $clientId);
    }

    public function adminTogglePortal(Request $request): Response
    {
        Auth::requirePermission('clients.view');
        $clientId = (int) $request->param('clientId');
        $client   = $this->clientRepo->findByIdAndAgency($clientId, (int) Auth::agencyId());

        if (!$client) return Response::view('errors.404', [], 404);

        $enabled = !(bool) $client['portal_enabled'];
        $this->clientRepo->setPortalEnabled($clientId, $enabled);
        $this->withSuccess($enabled ? 'Portal ativado.' : 'Portal desativado.');
        return $this->redirect('/clientes/' . $clientId);
    }
}
