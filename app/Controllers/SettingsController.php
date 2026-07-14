<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AgencyRepository;
use App\Services\ImageUploadService;
use App\Services\NotificationService;
use App\Support\Auth;

class SettingsController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AgencyRepository    $agencies,
        private readonly ImageUploadService  $images,
    ) {}

    // ── Agency settings ───────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        Auth::requirePermission('settings.manage');

        $agency = $this->agencies->find((int) Auth::agencyId());

        return $this->view('settings.index', compact('agency'));
    }

    public function save(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $agencyId = (int) Auth::agencyId();

        $name = trim((string) $request->post('name', ''));
        if (empty($name)) {
            $this->withError('O nome da agência é obrigatório.');
            return $this->redirect('/configuracoes');
        }

        $agency  = $this->agencies->find($agencyId);
        $logoUrl = $agency['logo_url'] ?? null;

        // Logotipo: arquivo enviado > remoção pedida > mantém o atual.
        $upload = $request->file('logo_file');
        if ($upload && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newUrl = $this->images->storeLogo($upload, 'agencia-' . $agencyId);
                $this->images->deleteLogo($logoUrl);   // não deixa lixo acumulando
                $logoUrl = $newUrl;
            } catch (\RuntimeException $e) {
                $this->withError($e->getMessage());
                return $this->redirect('/configuracoes');
            }
        } elseif ($request->post('remove_logo')) {
            $this->images->deleteLogo($logoUrl);
            $logoUrl = null;
        }

        // PROD-06: cor da marca — validada, porque vai direto para o CSS do portal.
        $brandColor = trim((string) $request->post('brand_color', ''));
        if ($brandColor !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $brandColor)) {
            $this->withError('Cor da marca inválida.');
            return $this->redirect('/configuracoes');
        }

        $this->agencies->updateProfile($agencyId, [
            'name'            => $name,
            'legal_name'      => trim((string) $request->post('legal_name', '')) ?: null,
            'document_number' => trim((string) $request->post('document_number', '')) ?: null,
            'email'           => trim((string) $request->post('email', '')) ?: null,
            'phone'           => trim((string) $request->post('phone', '')) ?: null,
            'website'         => trim((string) $request->post('website', '')) ?: null,
            'timezone'        => trim((string) $request->post('timezone', 'America/Sao_Paulo')),
            'language'        => trim((string) $request->post('language', 'pt')),
            'logo_url'        => $logoUrl,
            'brand_color'     => $brandColor ?: null,
        ]);

        // Aplica o idioma imediatamente na sessão (antes derivava do user, que não tem a coluna)
        $_SESSION['locale'] = \App\Core\Lang::normalize(trim((string) $request->post('language', 'pt')));

        $this->withSuccess('Configurações salvas.');
        return $this->redirect('/configuracoes');
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    public function notificationsIndex(Request $request): Response
    {
        Auth::requireLogin();

        $userId   = (int) Auth::id();
        $agencyId = (int) Auth::agencyId();

        $notifications = $this->notifications->unreadList($userId, $agencyId);
        return Response::json(['notifications' => $notifications]);
    }

    public function notificationsMarkRead(Request $request, int $id): Response
    {
        Auth::requireLogin();
        $this->notifications->markRead($id, (int) Auth::id());
        return Response::json(['success' => true]);
    }

    public function notificationsMarkAllRead(Request $request): Response
    {
        Auth::requireLogin();
        $this->notifications->markAllRead((int) Auth::id(), (int) Auth::agencyId());
        return Response::json(['success' => true]);
    }

    public function notificationsCount(Request $request): Response
    {
        Auth::requireLogin();
        $count = $this->notifications->unreadCount((int) Auth::id(), (int) Auth::agencyId());
        return Response::json(['count' => $count]);
    }
}
