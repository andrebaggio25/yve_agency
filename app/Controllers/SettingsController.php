<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;
use App\Support\Auth;

class SettingsController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notifRepo,
        private readonly NotificationService    $notifications,
    ) {}

    // ── Agency settings ───────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $pdo      = Database::connection();
        $agencyId = (int) Auth::agencyId();

        $agency = $pdo->prepare('SELECT * FROM agencies WHERE id = :id LIMIT 1');
        $agency->execute([':id' => $agencyId]);
        $agency = $agency->fetch(\PDO::FETCH_ASSOC);

        return $this->view('settings.index', compact('agency'));
    }

    public function save(Request $request): Response
    {
        Auth::requirePermission('settings.manage');
        $pdo      = Database::connection();
        $agencyId = (int) Auth::agencyId();

        $name     = trim((string) $request->post('name', ''));
        if (empty($name)) {
            $this->withError('O nome da agência é obrigatório.');
            return $this->redirect('/configuracoes');
        }

        $pdo->prepare("
            UPDATE agencies SET
                name            = :name,
                legal_name      = :legal_name,
                document_number = :doc_num,
                email           = :email,
                phone           = :phone,
                website         = :website,
                timezone        = :timezone,
                language        = :language,
                logo_url        = :logo_url,
                updated_at      = NOW()
            WHERE id = :id
        ")->execute([
            ':name'       => $name,
            ':legal_name' => trim((string) $request->post('legal_name', '')) ?: null,
            ':doc_num'    => trim((string) $request->post('document_number', '')) ?: null,
            ':email'      => trim((string) $request->post('email', '')) ?: null,
            ':phone'      => trim((string) $request->post('phone', '')) ?: null,
            ':website'    => trim((string) $request->post('website', '')) ?: null,
            ':timezone'   => trim((string) $request->post('timezone', 'America/Sao_Paulo')),
            ':language'   => trim((string) $request->post('language', 'pt')),
            ':logo_url'   => trim((string) $request->post('logo_url', '')) ?: null,
            ':id'         => $agencyId,
        ]);

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
