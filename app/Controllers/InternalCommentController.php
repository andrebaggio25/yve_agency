<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\InternalCommentService;
use App\Support\Auth;

class InternalCommentController extends Controller
{
    private const TYPE_PERMISSIONS = [
        'content_plan_item' => 'content.view',
        'content_plan'      => 'content.view',
        'task'              => 'tasks.view',
    ];

    public function __construct(
        private readonly InternalCommentService $service,
    ) {}

    public function index(Request $request): Response
    {
        $type     = $request->param('type');
        $entityId = (int) $request->param('entityId');
        $agencyId = (int) Auth::agencyId();

        $permission = self::TYPE_PERMISSIONS[$type] ?? null;
        if (!$permission) return Response::json(['error' => 'Tipo inválido'], 400);
        Auth::requirePermission($permission);

        $comments = $this->service->get($type, $entityId, $agencyId);

        return Response::json(['comments' => $comments]);
    }

    public function store(Request $request): Response
    {
        $type     = $request->param('type');
        $entityId = (int) $request->param('entityId');
        $agencyId = (int) Auth::agencyId();
        $message  = trim((string) $request->input('message', ''));

        $permission = self::TYPE_PERMISSIONS[$type] ?? null;
        if (!$permission) return Response::json(['error' => 'Tipo inválido'], 400);
        Auth::requirePermission($permission);

        if ($message === '') {
            return Response::json(['error' => 'Mensagem obrigatória'], 422);
        }

        try {
            $id = $this->service->add($type, $entityId, $agencyId, (int) Auth::id(), $message);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }

        $comment = [
            'id'          => $id,
            'message'     => $message,
            'user_name'   => Auth::user()['name'] ?? '',
            'user_avatar' => Auth::user()['avatar'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        return Response::json(['success' => true, 'comment' => $comment]);
    }
}
