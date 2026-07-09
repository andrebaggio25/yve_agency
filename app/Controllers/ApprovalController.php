<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Repositories\ContentPlanRepository;
use App\Services\ContentPlanService;
use App\Support\Auth;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ContentPlanService    $service,
        private readonly ContentPlanRepository $repo,
        private readonly ClientRepository      $clients,
    ) {}

    /** List plans awaiting approval */
    public function index(Request $request): Response
    {
        Auth::requirePermission('approvals.view');

        $clientIds = $_SESSION['client_ids'] ?? [];
        $agencyId  = (int) Auth::agencyId();
        $plans     = [];

        foreach ($clientIds as $clientId) {
            $clientPlans = $this->repo->allByClient((int) $clientId, $agencyId);
            $plans       = array_merge($plans, $clientPlans);
        }

        usort($plans, fn($a, $b) => strcmp($b['week_start'], $a['week_start']));

        return $this->view('approvals.index', compact('plans'));
    }

    /** Show a single plan for approval */
    public function show(Request $request): Response
    {
        Auth::requirePermission('approvals.view');

        $planId    = (int) $request->param('planId');
        $clientIds = $_SESSION['client_ids'] ?? [];
        $agencyId  = (int) Auth::agencyId();

        $plan     = null;
        $clientId = null;
        foreach ($clientIds as $cid) {
            $found = $this->repo->findByIdForClient($planId, (int) $cid);
            if ($found) { $plan = $found; $clientId = (int) $cid; break; }
        }

        if ($clientId) {
            $clientRow = $this->clients->findByIdAndAgency($clientId, $agencyId);
            if ($clientRow && !empty($clientRow['language'])) {
                Lang::setLocale($clientRow['language']);
            }
        }

        if (!$plan) return $this->view('errors.404', [], 404);

        $items = $this->repo->getItems($planId);
        foreach ($items as &$item) {
            $item['feedbacks']   = $this->repo->getFeedbacks((int) $item['id']);
            $item['images_list'] = is_string($item['images'] ?? null)
                ? (json_decode($item['images'], true) ?? [])
                : ($item['images'] ?? []);
        }
        unset($item);

        $plan['items'] = $items;

        return $this->view('approvals.show', compact('plan'));
    }

    /** Submit feedback on a single item */
    public function feedback(Request $request): Response
    {
        Auth::requirePermission('approvals.comment');

        $planId    = (int) $request->param('planId');
        $itemId    = (int) $request->param('itemId');
        $clientIds = $_SESSION['client_ids'] ?? [];
        $userId    = (int) Auth::id();

        $clientId = null;
        foreach ($clientIds as $cid) {
            $found = $this->repo->findByIdForClient($planId, (int) $cid);
            if ($found) { $clientId = (int) $cid; break; }
        }

        if (!$clientId) {
            return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }

        $item = $this->repo->findItemForClient($itemId, $clientId);
        if (!$item || (int) $item['content_plan_id'] !== $planId) {
            return Response::json(['success' => false, 'error' => 'Item não encontrado.'], 404);
        }

        $type    = $request->input('feedback_type', 'comment');
        $comment = $request->input('comment');

        $allowed = ['approved', 'changes_requested', 'rejected', 'comment'];
        if (!in_array($type, $allowed, true)) {
            return Response::json(['success' => false, 'error' => 'Tipo inválido.'], 422);
        }

        if ($type !== 'comment' && !Auth::can("approvals.{$type}")) {
            return Response::json(['success' => false, 'error' => 'Sem permissão.'], 403);
        }

        $fbId = $this->service->addFeedback($itemId, $planId, $clientId, $userId, $type, $comment);

        return Response::json(['success' => true, 'feedback_id' => $fbId]);
    }

    /** Approve entire plan */
    public function approvePlan(Request $request): Response
    {
        Auth::requirePermission('approvals.approve');

        $planId    = (int) $request->param('planId');
        $clientIds = $_SESSION['client_ids'] ?? [];

        foreach ($clientIds as $clientId) {
            $ok = $this->service->approvePlan($planId, (int) $clientId);
            if ($ok) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => true]);
                }
                $this->withSuccess('Plano aprovado com sucesso!');
                return $this->redirect("/aprovacoes/{$planId}");
            }
        }

        return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
    }

    /** Request revision on entire plan */
    public function requestRevision(Request $request): Response
    {
        Auth::requirePermission('approvals.approve');

        $planId    = (int) $request->param('planId');
        $clientIds = $_SESSION['client_ids'] ?? [];
        $note      = $request->input('note', '');

        foreach ($clientIds as $clientId) {
            $ok = $this->service->requestRevision($planId, (int) $clientId, $note);
            if ($ok) {
                if ($request->wantsJson()) {
                    return Response::json(['success' => true]);
                }
                $this->withSuccess('Solicitação de revisão enviada.');
                return $this->redirect("/aprovacoes/{$planId}");
            }
        }

        return Response::json(['success' => false, 'error' => 'Acesso negado.'], 403);
    }
}
